<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time-series engagement metrics captured per published post target.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('metric');           // impressions|likes|comments|shares|...
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['channel_id', 'metric', 'captured_at']);
            $table->index(['post_target_id', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_metrics');
    }
};
