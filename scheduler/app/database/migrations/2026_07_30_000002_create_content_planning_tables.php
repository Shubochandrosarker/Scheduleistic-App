<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaigns, content pillars and tags — the three axes the planner and
 * analytics slice content by.
 *
 * All three are workspace-scoped rather than organization-scoped: an agency
 * running the same campaign name for two clients must not have them collide,
 * and every authorization check in the app already resolves through a
 * workspace. The composite indexes mirror the queries the planner issues
 * (always `workspace_id` first, then the filter column).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('goal')->nullable();
            $table->string('status')->default('planning'); // planning|active|paused|completed|archived
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedBigInteger('budget_cents')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('target_url', 2048)->nullable();
            $table->json('utm')->nullable();       // source/medium/campaign/term/content
            $table->json('kpi_targets')->nullable(); // e.g. {"impressions": 50000}
            $table->string('color', 9)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'starts_on']);
            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('campaign_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();

            $table->unique(['campaign_id', 'user_id']);
        });

        Schema::create('content_pillars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('color', 9)->nullable();
            // The share of the calendar this pillar should occupy, so the
            // planner can show "you are 40% education vs a 25% target".
            $table->unsignedTinyInteger('target_share')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'position']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 9)->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_pillar_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            // inbox|researching|drafting|ready|scheduled|published|archived
            $table->string('stage')->default('inbox');
            $table->string('priority')->default('normal'); // low|normal|high|urgent
            $table->string('source_url', 2048)->nullable();
            $table->json('attachments')->nullable();
            $table->date('due_on')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'stage', 'position']);
            $table->index(['workspace_id', 'assignee_id']);
        });

        Schema::create('idea_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['idea_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_tag');
        Schema::dropIfExists('ideas');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('content_pillars');
        Schema::dropIfExists('campaign_user');
        Schema::dropIfExists('campaigns');
    }
};
