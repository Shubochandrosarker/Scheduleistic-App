<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Post is the unit of authoring inside a Workspace. It is composed once and
 * fanned out to one or more channels (each gets a PostVersion + PostTarget).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('group_id')->index();        // idempotency / grouping across targets
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft'); // draft|pending_approval|scheduled|publishing|published|partially_failed|failed
            $table->text('content')->nullable();        // base content (per-channel overrides live in versions)
            $table->json('media')->nullable();          // base media references
            $table->string('source')->default('manual'); // manual|rss|wordpress|ai
            $table->timestamp('scheduled_at')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('approval_state')->nullable(); // null|pending|approved|rejected|changes_requested
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
