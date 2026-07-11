<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storage for the post AI agents (App\Services\PostAiAgents): hashtags
 * suggested/written back by the hashtag agent, and the readiness report
 * written back by the quality-check agent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->json('hashtags')->nullable()->after('media');
            $table->json('ai_quality_report')->nullable()->after('hashtags');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['hashtags', 'ai_quality_report']);
        });
    }
};
