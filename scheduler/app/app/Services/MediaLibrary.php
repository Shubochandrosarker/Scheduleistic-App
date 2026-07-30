<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\Tag;
use App\Models\Team;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores and accounts for library assets.
 *
 * Ingestion is deliberately split: this class does only the cheap, synchronous
 * part (checksum, quota, write the original, create the row) and hands the
 * expensive part — probing dimensions, generating derivatives, virus scanning —
 * to ProcessMediaAssetJob. An HTTP request never waits on image encoding.
 */
class MediaLibrary
{
    public function __construct(private readonly PlanService $plans) {}

    /**
     * Ingest an upload.
     *
     * Re-uploading identical bytes into the same workspace returns the existing
     * asset instead of creating a duplicate. That makes the endpoint idempotent
     * under a retried request and is also the duplicate detection the brief
     * asks for — the two are the same mechanism.
     *
     * @param  array{alt_text?:?string, notes?:?string, media_folder_id?:?int, tags?:array<int,string>, source?:string}  $attributes
     */
    public function storeUpload(Workspace $workspace, UploadedFile $file, array $attributes = [], ?int $userId = null): MediaAsset
    {
        $checksum = hash_file('sha256', $file->getRealPath());

        $existing = MediaAsset::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->where('checksum', $checksum)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $this->applyAttributes($existing, $attributes);

            return $existing;
        }

        $disk = config('media.disk');
        $kind = $this->kindFor($file->getMimeType() ?? '');

        // One directory per asset keeps the original and its derivatives
        // together, so deleting an asset is a single prefix removal.
        $directory = sprintf('media/%d/%s', $workspace->id, Str::uuid());
        $path      = $file->storeAs($directory, 'original.'.$this->extensionFor($file), ['disk' => $disk]);

        $asset = new MediaAsset([
            'workspace_id'      => $workspace->id,
            'media_folder_id'   => $attributes['media_folder_id'] ?? null,
            'uploaded_by'       => $userId,
            'name'              => $this->displayName($file),
            'kind'              => $kind,
            'mime'              => $file->getMimeType() ?? 'application/octet-stream',
            'disk'              => $disk,
            'path'              => $path,
            'size'              => $file->getSize() ?: 0,
            'checksum'          => $checksum,
            'alt_text'          => $attributes['alt_text'] ?? null,
            'notes'             => $attributes['notes'] ?? null,
            'source'            => $attributes['source'] ?? 'upload',
            'processing_status' => MediaAsset::STATUS_PENDING,
        ]);

        $asset->save();

        $this->syncTags($asset, $attributes['tags'] ?? []);

        return $asset;
    }

    /** @param array<int, string> $names */
    public function syncTags(MediaAsset $asset, array $names): void
    {
        if ($names === []) {
            return;
        }

        $ids = collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name) => Tag::resolve($asset->workspace_id, $name)->id)
            ->all();

        $asset->tags()->sync($ids);
    }

    /** Delete an asset and every object it owns. */
    public function delete(MediaAsset $asset): void
    {
        DB::transaction(function () use ($asset) {
            $disk = Storage::disk($asset->disk);

            foreach ($asset->allPaths() as $path) {
                $disk->delete($path);
            }

            // The per-asset directory holds nothing else.
            $disk->deleteDirectory(dirname($asset->path));

            $asset->delete();
        });
    }

    // --- Quota ----------------------------------------------------------

    /** Bytes currently stored across every workspace in the organization. */
    public function usedBytes(Team $team): int
    {
        return (int) MediaAsset::query()
            ->whereIn('workspace_id', $team->workspaces()->select('id'))
            ->sum('size');
    }

    /** The organization's storage allowance in bytes, or null when unlimited. */
    public function quotaBytes(Team $team): ?int
    {
        $mb = $this->plans->limit($team, 'storage_mb');

        return $mb === PlanService::UNLIMITED ? null : $mb * 1024 * 1024;
    }

    public function hasRoomFor(Team $team, int $bytes): bool
    {
        $quota = $this->quotaBytes($team);

        if ($quota === null) {
            return true;
        }

        return ($this->usedBytes($team) + $bytes) <= $quota;
    }

    public function quotaLabel(Team $team): string
    {
        $quota = $this->quotaBytes($team);

        return $quota === null ? 'unlimited' : $this->humanBytes($quota);
    }

    /** @return array{used:int, quota:?int, used_label:string, quota_label:string, percent:?int} */
    public function quotaSnapshot(Team $team): array
    {
        $used  = $this->usedBytes($team);
        $quota = $this->quotaBytes($team);

        return [
            'used'        => $used,
            'quota'       => $quota,
            'used_label'  => $this->humanBytes($used),
            'quota_label' => $this->quotaLabel($team),
            'percent'     => $quota ? min(100, (int) round($used / max($quota, 1) * 100)) : null,
        ];
    }

    public function humanBytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024 || $unit === 'TB') {
                return $unit === 'B'
                    ? $bytes.' B'
                    : sprintf('%.1f %s', $bytes, $unit);
            }

            $bytes /= 1024;
        }

        return $bytes.' B';
    }

    // --- Helpers --------------------------------------------------------

    protected function applyAttributes(MediaAsset $asset, array $attributes): void
    {
        $asset->fill(array_filter([
            'alt_text'        => $attributes['alt_text'] ?? null,
            'notes'           => $attributes['notes'] ?? null,
            'media_folder_id' => $attributes['media_folder_id'] ?? null,
        ], fn ($v) => $v !== null))->save();

        $this->syncTags($asset, $attributes['tags'] ?? []);
    }

    protected function kindFor(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => MediaAsset::KIND_IMAGE,
            str_starts_with($mime, 'video/') => MediaAsset::KIND_VIDEO,
            default                          => MediaAsset::KIND_DOCUMENT,
        };
    }

    /** Never trust the client-supplied filename for the stored extension. */
    protected function extensionFor(UploadedFile $file): string
    {
        $guessed = $file->guessExtension();

        return preg_match('/^[a-z0-9]{1,5}$/i', (string) $guessed) ? strtolower($guessed) : 'bin';
    }

    /** The original filename is display-only and is sanitised before storage. */
    protected function displayName(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        return Str::limit(trim(preg_replace('/[^\p{L}\p{N}\s._-]+/u', '', $name) ?: 'Untitled'), 150, '');
    }
}
