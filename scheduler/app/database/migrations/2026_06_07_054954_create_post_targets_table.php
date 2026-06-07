<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One delivery of a Post to one Channel. The publishing engine creates a
 * PublishPostJob per target, so each network's outcome is tracked
 * independently (one can fail while others succeed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending|publishing|published|failed
            $table->string('provider_post_id')->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'channel_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_targets');
    }
};
