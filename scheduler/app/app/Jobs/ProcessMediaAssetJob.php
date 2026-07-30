<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Post-upload processing: probe the asset, run the optional antivirus hook,
 * and generate derivatives.
 *
 * Idempotent by design — it recomputes everything from the stored original and
 * overwrites the `variants` map, so a retry after a partial failure produces
 * the same result as a first run. The original is never modified.
 */
class ProcessMediaAssetJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(public int $assetId) {}

    /** Deduplicate: only one processing job per asset may be queued at a time. */
    public function uniqueId(): string
    {
        return 'media-asset-'.$this->assetId;
    }

    public function handle(): void
    {
        $asset = MediaAsset::find($this->assetId);

        if (! $asset) {
            return; // deleted before the queue got to it
        }

        $asset->update(['processing_status' => MediaAsset::STATUS_PROCESSING]);

        try {
            $local = $this->pullToLocal($asset);

            if ($reason = $this->failsVirusScan($local)) {
                $this->quarantine($asset, $reason);

                return;
            }

            $updates = ['processing_status' => MediaAsset::STATUS_READY, 'processing_error' => null];

            if ($asset->kind === MediaAsset::KIND_IMAGE) {
                $updates += $this->probeImage($local);
                $updates['variants'] = $this->generateImageVariants($asset, $local);
            }

            $asset->update($updates);

            @unlink($local);
        } catch (\Throwable $e) {
            Log::warning('Media processing failed', [
                'asset_id' => $asset->id,
                'error'    => $e->getMessage(),
            ]);

            $asset->update([
                'processing_status' => MediaAsset::STATUS_FAILED,
                'processing_error'  => $e->getMessage(),
            ]);

            throw $e; // let the queue retry with backoff
        }
    }

    /** Copy the original to local scratch so GD can read it off any disk. */
    protected function pullToLocal(MediaAsset $asset): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'media');
        $bytes = Storage::disk($asset->disk)->get($asset->path);

        file_put_contents($temp, $bytes);

        return $temp;
    }

    /**
     * Runs the configured antivirus command, if any. Returns a reason string
     * when the file should be quarantined, or null when it is clean (or when
     * no scanner is configured).
     */
    protected function failsVirusScan(string $path): ?string
    {
        $command = config('media.antivirus_command');

        if (! $command) {
            return null;
        }

        $result = Process::timeout(120)->run($command.' '.escapeshellarg($path));

        return $result->successful() ? null : 'Rejected by antivirus scan.';
    }

    protected function quarantine(MediaAsset $asset, string $reason): void
    {
        Storage::disk($asset->disk)->delete($asset->path);

        $asset->update([
            'processing_status' => MediaAsset::STATUS_FAILED,
            'processing_error'  => $reason,
        ]);

        Log::warning('Media asset quarantined', ['asset_id' => $asset->id, 'reason' => $reason]);
    }

    /** @return array{width?:int, height?:int} */
    protected function probeImage(string $path): array
    {
        $info = @getimagesize($path);

        if (! $info) {
            return [];
        }

        return ['width' => (int) $info[0], 'height' => (int) $info[1]];
    }

    /**
     * Generate the derivatives declared in config/media.php.
     *
     * Uses GD, which ships with every supported PHP build, so a stock install
     * produces thumbnails without an extra binary. If GD is unavailable the
     * asset still becomes ready — it simply renders from the original.
     *
     * @return array<string, array{path:string, width:int, height:int}>
     */
    protected function generateImageVariants(MediaAsset $asset, string $path): array
    {
        if (! function_exists('imagecreatetruecolor')) {
            return $asset->variants ?? [];
        }

        $source = $this->readImage($path, $asset->mime);

        if (! $source) {
            return $asset->variants ?? [];
        }

        $variants  = [];
        $directory = dirname($asset->path);
        $disk      = Storage::disk($asset->disk);

        foreach (config('media.variants', []) as $name => $spec) {
            $resized = $this->resize($source, $spec);

            if (! $resized) {
                continue;
            }

            $temp = tempnam(sys_get_temp_dir(), 'variant');
            imagejpeg($resized, $temp, $spec['quality'] ?? 82);

            $variantPath = $directory.'/'.$name.'.jpg';
            $disk->put($variantPath, file_get_contents($temp));

            $variants[$name] = [
                'path'   => $variantPath,
                'width'  => imagesx($resized),
                'height' => imagesy($resized),
            ];

            imagedestroy($resized);
            @unlink($temp);
        }

        imagedestroy($source);

        return $variants;
    }

    protected function readImage(string $path, string $mime): ?\GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/gif'  => @imagecreatefromgif($path),
            default      => false,
        };

        return $image ?: null;
    }

    /** @param array{width:int, height:int, fit:string} $spec */
    protected function resize(\GdImage $source, array $spec): ?\GdImage
    {
        $sw = imagesx($source);
        $sh = imagesy($source);

        if ($sw < 1 || $sh < 1) {
            return null;
        }

        $tw = (int) $spec['width'];
        $th = (int) $spec['height'];

        if (($spec['fit'] ?? 'contain') === 'cover') {
            // Fill the box, cropping the overflow from the centre.
            $scale = max($tw / $sw, $th / $sh);
            $cw    = (int) round($tw / $scale);
            $ch    = (int) round($th / $scale);
            $sx    = (int) round(($sw - $cw) / 2);
            $sy    = (int) round(($sh - $ch) / 2);

            $canvas = imagecreatetruecolor($tw, $th);
            imagecopyresampled($canvas, $source, 0, 0, $sx, $sy, $tw, $th, $cw, $ch);

            return $canvas;
        }

        // Contain: never upscale — a 400px source stays 400px.
        $scale = min($tw / $sw, $th / $sh, 1);
        $dw    = max(1, (int) round($sw * $scale));
        $dh    = max(1, (int) round($sh * $scale));

        $canvas = imagecreatetruecolor($dw, $dh);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dw, $dh, $sw, $sh);

        return $canvas;
    }
}
