<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Channel is a single connected social account inside a Workspace
 * (e.g. a LinkedIn company page, a Facebook page, an Instagram account).
 * OAuth tokens are encrypted at rest via the model's casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('provider');               // linkedin, facebook, instagram, ...
            $table->string('provider_account_id')->nullable();
            $table->string('name');
            $table->string('avatar')->nullable();
            $table->text('access_token')->nullable();  // encrypted cast
            $table->text('refresh_token')->nullable(); // encrypted cast
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('meta')->nullable();
            $table->string('status')->default('connected'); // connected|expired|error|disconnected
            $table->timestamps();

            $table->index(['workspace_id', 'provider']);
            $table->unique(['workspace_id', 'provider', 'provider_account_id'], 'channels_unique_account');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
