<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMediaAssetRequest;
use App\Http\Requests\StoreMediaFolderRequest;
use App\Http\Requests\UpdateMediaAssetRequest;
use App\Jobs\ProcessMediaAssetJob;
use App\Models\MediaAsset;
use App\Models\MediaFolder;
use App\Services\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The media library.
 *
 * Uploads return immediately: the file is stored and the row created
 * synchronously, then ProcessMediaAssetJob does the probing, scanning and
 * derivative generation. The client polls `status` for the transition to ready.
 */
class MediaAssetController extends Controller
{
    public function __construct(private readonly MediaLibrary $library) {}

    public function index(Request $request): Response
    {
        $visible = $request->user()->visibleWorkspaceIds();

        $validated = $request->validate([
            'workspace_id' => ['nullable', 'integer', Rule::in($visible->all())],
            'folder_id'    => ['nullable', 'integer'],
            'kind'         => ['nullable', Rule::in([MediaAsset::KIND_IMAGE, MediaAsset::KIND_VIDEO, MediaAsset::KIND_DOCUMENT])],
            'search'       => ['nullable', 'string', 'max:200'],
            'favorites'    => ['nullable', 'boolean'],
            'archived'     => ['nullable', 'boolean'],
        ]);

        $query = MediaAsset::query()
            ->forWorkspaces($visible)
            ->with(['tags:id,name,color', 'uploader:id,name', 'workspace:id,name'])
            ->when($validated['workspace_id'] ?? null, fn ($q, $id) => $q->where('workspace_id', $id))
            ->when(array_key_exists('folder_id', $validated), fn ($q) => $q->where('media_folder_id', $validated['folder_id']))
            ->when($validated['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($validated['favorites'] ?? false, fn ($q) => $q->where('is_favorite', true))
            ->when(
                $validated['archived'] ?? false,
                fn ($q) => $q->whereNotNull('archived_at'),
                fn ($q) => $q->whereNull('archived_at'),
            );

        if ($search = trim((string) ($validated['search'] ?? ''))) {
            // Explicit ESCAPE clause — see PlannerQuery for why it cannot be
            // left implicit across SQLite/MySQL/Postgres.
            $escaped = addcslashes($search, '%_\\');
            $query->where(fn ($q) => $q
                ->whereRaw('name LIKE ? ESCAPE ?', ["%{$escaped}%", '\\'])
                ->orWhereRaw('alt_text LIKE ? ESCAPE ?', ["%{$escaped}%", '\\']));
        }

        $assets = $query->latest()->paginate(48)->withQueryString();

        // URLs are generated here, not stored, so a signed URL is always fresh
        // and an object key is never exposed to the client.
        $assets->getCollection()->transform(fn (MediaAsset $asset) => [
            ...$asset->only([
                'id', 'workspace_id', 'media_folder_id', 'name', 'kind', 'mime', 'size',
                'width', 'height', 'duration', 'alt_text', 'notes', 'source',
                'processing_status', 'processing_error', 'usage_count', 'is_favorite',
            ]),
            'archived'   => $asset->archived_at !== null,
            'created_at' => $asset->created_at?->toIso8601String(),
            'size_label' => $this->library->humanBytes((int) $asset->size),
            'url'        => $asset->url(),
            'thumb_url'  => $asset->url('thumb') ?? $asset->url(),
            'tags'       => $asset->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color]),
            'uploader'   => $asset->uploader?->name,
            'workspace'  => $asset->workspace?->name,
        ]);

        return Inertia::render('Media/Index', [
            'assets'     => $assets,
            'folders'    => MediaFolder::whereIn('workspace_id', $visible)->orderBy('name')->get(['id', 'workspace_id', 'parent_id', 'name']),
            'workspaces' => $request->user()->currentTeam?->workspaces()->get(['id', 'name', 'color']) ?? [],
            'quota'      => $request->user()->currentTeam
                ? $this->library->quotaSnapshot($request->user()->currentTeam)
                : null,
            'sources' => collect(config('media.sources', []))
                ->map(fn ($s, $key) => ['key' => $key] + $s)
                ->values(),
            'filters' => $validated,
        ]);
    }

    public function store(StoreMediaAssetRequest $request): RedirectResponse
    {
        $asset = $this->library->storeUpload(
            $request->workspace(),
            $request->file('file'),
            $request->safe()->only(['alt_text', 'notes', 'media_folder_id', 'tags']),
            $request->user()->id,
        );

        // Probing, scanning and derivative generation never block the request.
        ProcessMediaAssetJob::dispatch($asset->id);

        return back()->with('status', 'asset-uploaded');
    }

    /** Poll target while an upload is being processed. */
    public function status(Request $request, MediaAsset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        return response()->json([
            'id'        => $asset->id,
            'status'    => $asset->processing_status,
            'error'     => $asset->processing_error,
            'width'     => $asset->width,
            'height'    => $asset->height,
            'thumb_url' => $asset->url('thumb') ?? $asset->url(),
        ]);
    }

    public function update(UpdateMediaAssetRequest $request, MediaAsset $asset): RedirectResponse
    {
        $asset->update([
            ...$request->safe()->only(['name', 'alt_text', 'notes', 'is_favorite']),
            ...($request->has('archived')
                ? ['archived_at' => $request->boolean('archived') ? now() : null]
                : []),
        ]);

        if ($request->has('tags')) {
            $this->library->syncTags($asset, $request->input('tags', []));
        }

        return back()->with('status', 'asset-updated');
    }

    public function destroy(Request $request, MediaAsset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);

        $this->library->delete($asset);

        return back()->with('status', 'asset-deleted');
    }

    public function storeFolder(StoreMediaFolderRequest $request): RedirectResponse
    {
        MediaFolder::create($request->safe()->all());

        return back()->with('status', 'folder-created');
    }

    public function destroyFolder(Request $request, MediaFolder $folder): RedirectResponse
    {
        $this->authorize('delete', $folder);

        // Assets survive their folder — they fall back to the library root.
        $folder->assets()->update(['media_folder_id' => null]);
        $folder->delete();

        return back()->with('status', 'folder-deleted');
    }
}
