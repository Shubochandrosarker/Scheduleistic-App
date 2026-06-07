<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BulkImporter;
use App\Services\QueueScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QueueAndImportTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWithChannels(): Workspace
    {
        $user = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create([
            'team_id'  => $user->currentTeam->id,
            'name'     => 'Acme',
            'timezone' => 'UTC',
        ]);
        $workspace->channels()->create(['provider' => 'linkedin', 'name' => 'LI']);
        $workspace->channels()->create(['provider' => 'facebook', 'name' => 'FB']);

        return $workspace;
    }

    public function test_next_slot_uses_the_workspace_time_slot_templates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 08:00:00')); // a Monday
        $workspace = $this->workspaceWithChannels();

        // Add a Monday 09:00 slot (day_of_week 1).
        $workspace->timeSlots()->create(['day_of_week' => 1, 'time' => '09:00']);

        $slot = app(QueueScheduler::class)->nextSlot($workspace);

        $this->assertSame('2026-06-08 09:00:00', $slot->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_next_slot_skips_a_taken_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 08:00:00'));
        $workspace = $this->workspaceWithChannels();
        $workspace->timeSlots()->create(['day_of_week' => 1, 'time' => '09:00']); // Mon
        $workspace->timeSlots()->create(['day_of_week' => 2, 'time' => '09:00']); // Tue

        // Occupy Monday 09:00.
        Post::create([
            'workspace_id' => $workspace->id,
            'status'       => Post::STATUS_SCHEDULED,
            'content'      => 'taken',
            'scheduled_at' => Carbon::parse('2026-06-08 09:00:00'),
        ]);

        $slot = app(QueueScheduler::class)->nextSlot($workspace);

        $this->assertSame('2026-06-09 09:00:00', $slot->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_csv_parsing_and_bulk_import_create_posts(): void
    {
        $workspace = $this->workspaceWithChannels();

        $csv = "content,scheduled_at,providers\n"
             ."\"First post\",2026-06-10 10:00:00,linkedin\n"
             ."\"Second post\",,linkedin|facebook\n"
             .",,linkedin\n"; // empty content → skipped

        $importer = app(BulkImporter::class);
        $rows = $importer->parseCsv($csv);
        $this->assertCount(3, $rows);

        $result = $importer->import($workspace, $rows);

        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseCount('posts', 2);

        // First post targets only LinkedIn; second targets both channels.
        $first = Post::where('content', 'First post')->first();
        $this->assertCount(1, $first->targets);
    }
}
