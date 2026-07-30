<?php

namespace Tests\Feature;

use App\Jobs\ProcessMediaAssetJob;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['media.disk' => 'local']);

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->owner->currentTeam->update(['plan' => 'pro']);
        $this->workspace = Workspace::create(['team_id' => $this->owner->currentTeam->id, 'name' => 'Brand']);
    }

    protected function image(string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 1200, 800);
    }

    // --- Upload -----------------------------------------------------------

    public function test_an_upload_is_stored_and_queued_for_processing(): void
    {
        Bus::fake();

        $this->actingAs($this->owner)
            ->post(route('media.store'), [
                'workspace_id' => $this->workspace->id,
                'file'         => $this->image(),
                'alt_text'     => 'A product on a table',
            ])
            ->assertSessionHasNoErrors();

        $asset = MediaAsset::first();

        $this->assertNotNull($asset);
        $this->assertSame($this->workspace->id, $asset->workspace_id);
        $this->assertSame(MediaAsset::KIND_IMAGE, $asset->kind);
        $this->assertSame('A product on a table', $asset->alt_text);
        $this->assertSame(MediaAsset::STATUS_PENDING, $asset->processing_status);

        Storage::disk('local')->assertExists($asset->path);

        // The HTTP request must never do the expensive work itself.
        Bus::assertDispatched(ProcessMediaAssetJob::class);
    }

    public function test_uploading_identical_bytes_twice_returns_the_same_asset(): void
    {
        Bus::fake();

        $file = $this->image();

        $this->actingAs($this->owner)->post(route('media.store'), [
            'workspace_id' => $this->workspace->id,
            'file'         => $file,
        ]);

        // Same bytes again — a retried request, or a genuine duplicate upload.
        $this->actingAs($this->owner)->post(route('media.store'), [
            'workspace_id' => $this->workspace->id,
            'file'         => UploadedFile::fake()->createWithContent('again.jpg', $file->get()),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, MediaAsset::count(), 'Identical bytes must deduplicate within a workspace.');
    }

    public function test_an_svg_upload_is_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $this->actingAs($this->owner)
            ->post(route('media.store'), ['workspace_id' => $this->workspace->id, 'file' => $svg])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, MediaAsset::count());
    }

    public function test_an_executable_disguised_as_an_image_is_rejected(): void
    {
        // The extension says JPEG; the content does not. `mimetypes:` reads
        // the content, which is the whole point of using it over `mimes:`.
        //
        // A real UploadedFile is constructed here rather than a fake, because
        // Laravel's fake files report a mime type derived from the *filename* —
        // which would let this test pass without proving anything.
        $path = tempnam(sys_get_temp_dir(), 'payload');
        file_put_contents($path, "#!/bin/sh\necho pwned\n");

        $disguised = new UploadedFile($path, 'payload.jpg', null, null, true);

        $this->actingAs($this->owner)
            ->post(route('media.store'), ['workspace_id' => $this->workspace->id, 'file' => $disguised])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, MediaAsset::count());
    }

    public function test_an_upload_that_would_exceed_the_storage_quota_is_rejected(): void
    {
        // Shrink Pro's allowance rather than dropping to Free, because Free
        // has no media library at all and would fail on the capability gate
        // instead of the quota — which is not what this test is about.
        config(['plans.pro.limits.storage_mb' => 200]);

        MediaAsset::create([
            'workspace_id'      => $this->workspace->id,
            'name'              => 'existing',
            'kind'              => MediaAsset::KIND_IMAGE,
            'mime'              => 'image/jpeg',
            'disk'              => 'local',
            'path'              => 'media/existing.jpg',
            'size'              => 200 * 1024 * 1024,
            'checksum'          => hash('sha256', 'existing'),
            'processing_status' => MediaAsset::STATUS_READY,
        ]);

        $this->actingAs($this->owner)
            ->post(route('media.store'), ['workspace_id' => $this->workspace->id, 'file' => $this->image()])
            ->assertSessionHasErrors('file');

        $this->assertSame(1, MediaAsset::count());
    }

    // --- Processing -------------------------------------------------------

    public function test_the_processing_job_probes_dimensions_and_generates_variants(): void
    {
        $this->actingAs($this->owner)->post(route('media.store'), [
            'workspace_id' => $this->workspace->id,
            'file'         => $this->image(),
        ]);

        $asset = MediaAsset::first();

        (new ProcessMediaAssetJob($asset->id))->handle();

        $asset->refresh();

        $this->assertSame(MediaAsset::STATUS_READY, $asset->processing_status);
        $this->assertSame(1200, $asset->width);
        $this->assertSame(800, $asset->height);
        $this->assertArrayHasKey('thumb', $asset->variants ?? []);

        // The original must survive untouched alongside its derivatives.
        Storage::disk('local')->assertExists($asset->path);
        Storage::disk('local')->assertExists($asset->variants['thumb']['path']);
    }

    public function test_processing_is_idempotent(): void
    {
        $this->actingAs($this->owner)->post(route('media.store'), [
            'workspace_id' => $this->workspace->id,
            'file'         => $this->image(),
        ]);

        $asset = MediaAsset::first();

        (new ProcessMediaAssetJob($asset->id))->handle();
        $first = $asset->fresh()->variants;

        (new ProcessMediaAssetJob($asset->id))->handle();
        $second = $asset->fresh()->variants;

        $this->assertEquals($first, $second);
    }

    public function test_the_status_endpoint_reports_processing_progress(): void
    {
        Bus::fake();

        $this->actingAs($this->owner)->post(route('media.store'), [
            'workspace_id' => $this->workspace->id,
            'file'         => $this->image(),
        ]);

        $asset = MediaAsset::first();

        $this->actingAs($this->owner)
            ->getJson(route('media.status', $asset))
            ->assertOk()
            ->assertJsonPath('status', MediaAsset::STATUS_PENDING);
    }

    // --- Library management -----------------------------------------------

    public function test_an_asset_can_be_renamed_favourited_and_archived(): void
    {
        Bus::fake();

        $this->actingAs($this->owner)->post(route('media.store'), [
            'workspace_id' => $this->workspace->id,
            'file'         => $this->image(),
        ]);

        $asset = MediaAsset::first();

        $this->actingAs($this->owner)
            ->put(route('media.update', $asset), [
                'name'        => 'Hero image',
                'alt_text'    => 'Updated alt text',
                'is_favorite' => true,
                'archived'    => true,
            ])
            ->assertSessionHasNoErrors();

        $asset->refresh();

        $this->assertSame('Hero image', $asset->name);
        $this->assertTrue($asset->is_favorite);
        $this->assertNotNull($asset->archived_at);
    }

    public function test_deleting_an_asset_removes_its_stored_objects(): void
    {
        $this->actingAs($this->owner)->post(route('media.store'), [
            'workspace_id' => $this->workspace->id,
            'file'         => $this->image(),
        ]);

        $asset = MediaAsset::first();
        (new ProcessMediaAssetJob($asset->id))->handle();
        $asset->refresh();

        $paths = $asset->allPaths();
        $this->assertGreaterThan(1, count($paths));

        $this->actingAs($this->owner)->delete(route('media.destroy', $asset))->assertSessionHasNoErrors();

        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    // --- Quota accounting -------------------------------------------------

    public function test_quota_accounting_covers_every_workspace_in_the_account(): void
    {
        $second = Workspace::create(['team_id' => $this->owner->currentTeam->id, 'name' => 'Second']);

        foreach ([$this->workspace, $second] as $index => $workspace) {
            MediaAsset::create([
                'workspace_id'      => $workspace->id,
                'name'              => "asset-{$index}",
                'kind'              => MediaAsset::KIND_IMAGE,
                'mime'              => 'image/jpeg',
                'disk'              => 'local',
                'path'              => "media/{$workspace->id}/original.jpg",
                'size'              => 1024 * 1024,
                'checksum'          => hash('sha256', "asset-{$index}"),
                'processing_status' => MediaAsset::STATUS_READY,
            ]);
        }

        $library = app(MediaLibrary::class);

        $this->assertSame(2 * 1024 * 1024, $library->usedBytes($this->owner->currentTeam));
        $this->assertSame('2.0 MB', $library->quotaSnapshot($this->owner->currentTeam)['used_label']);
    }

    // --- Plan gating ------------------------------------------------------

    public function test_the_media_library_is_unavailable_below_pro(): void
    {
        $this->owner->currentTeam->update(['plan' => 'solo']);

        $this->actingAs($this->owner)
            ->get(route('media.index'))
            ->assertSessionHasErrors('plan');
    }
}
