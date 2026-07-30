<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The media library.
 *
 * Assets are workspace-scoped so a client's imagery can never leak into
 * another client's picker. `checksum` is a SHA-256 of the uploaded bytes and is
 * unique per workspace, which is what makes duplicate detection cheap and
 * re-uploads idempotent.
 *
 * `variants` holds the derivatives the queue generates (thumbnail, optimised
 * web copy, per-network crops, video poster frame). The original is never
 * touched — every derivative is a new object under the same directory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index(['workspace_id', 'parent_id']);
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_folder_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->string('kind');                    // image|video|document
            $table->string('mime');
            $table->string('disk')->default('local');
            $table->string('path');                    // original, never overwritten
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->float('duration')->nullable();     // seconds, video only
            $table->string('checksum', 64)->nullable();

            $table->text('alt_text')->nullable();
            $table->text('notes')->nullable();         // internal, never published
            $table->string('source')->default('upload'); // upload|canva|drive|dropbox|unsplash|giphy
            $table->string('source_ref')->nullable();

            $table->json('variants')->nullable();
            $table->string('processing_status')->default('pending'); // pending|processing|ready|failed
            $table->text('processing_error')->nullable();

            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'kind']);
            $table->index(['workspace_id', 'media_folder_id']);
            $table->index(['workspace_id', 'archived_at']);
            $table->index(['workspace_id', 'last_used_at']);
            // Duplicate detection + idempotent re-upload, scoped to the tenant.
            $table->unique(['workspace_id', 'checksum']);
        });

        Schema::create('media_asset_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['media_asset_id', 'tag_id']);
        });

        // Which assets a post uses — an explicit relationship rather than a
        // polymorphic one, so usage counts and authorization both index well.
        Schema::create('media_asset_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->nullable(); // null = the base post, otherwise a per-network override
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['post_id', 'provider']);
            $table->index('media_asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_asset_post');
        Schema::dropIfExists('media_asset_tag');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('media_folders');
    }
};
