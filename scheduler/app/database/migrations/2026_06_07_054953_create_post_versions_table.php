<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-channel customization of a Post. When a post targets several networks,
 * each can have its own content, media, and provider-specific options
 * (first comment, thread items, board, location, alt text, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('provider');               // which network this version is for
            $table->text('content')->nullable();
            $table->json('media')->nullable();
            $table->json('options')->nullable();      // provider-specific options
            $table->timestamps();

            $table->unique(['post_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_versions');
    }
};
