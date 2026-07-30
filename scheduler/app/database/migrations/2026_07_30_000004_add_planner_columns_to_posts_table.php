<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wires posts into the planner: a campaign, a content pillar, an assignee and
 * a short internal title so a calendar card has something to show besides the
 * first 40 characters of the caption.
 *
 * The composite indexes are the point of this migration as much as the
 * columns — the planner's default query is
 * `where workspace_id in (…) and scheduled_at between … order by scheduled_at`,
 * and every filter narrows on `workspace_id` first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('workspace_id')->constrained()->nullOnDelete();
            $table->foreignId('content_pillar_id')->nullable()->after('campaign_id')->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->after('author_id')->constrained('users')->nullOnDelete();
            $table->foreignId('idea_id')->nullable()->after('assignee_id')->constrained()->nullOnDelete();
            $table->string('title')->nullable()->after('status');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->index(['workspace_id', 'scheduled_at']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'campaign_id']);
            $table->index(['workspace_id', 'assignee_id']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'scheduled_at']);
            $table->dropIndex(['workspace_id', 'status']);
            $table->dropIndex(['workspace_id', 'campaign_id']);
            $table->dropIndex(['workspace_id', 'assignee_id']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropConstrainedForeignId('content_pillar_id');
            $table->dropConstrainedForeignId('assignee_id');
            $table->dropConstrainedForeignId('idea_id');
            $table->dropColumn('title');
        });
    }
};
