<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RSS / WordPress article feeds that auto-draft social posts for a workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('name')->nullable();
            $table->boolean('active')->default(true);
            $table->json('seen_guids')->nullable();   // de-dupe already-ingested items
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeds');
    }
};
