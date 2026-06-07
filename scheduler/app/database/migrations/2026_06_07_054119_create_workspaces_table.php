<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Workspace represents a single client / brand inside an organization
 * (a Jetstream "team"). It isolates that client's channels, posts, media,
 * and approvals from every other client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')          // organization (Jetstream team)
                ->constrained('teams')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('client_name')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('color', 9)->nullable();
            $table->string('logo')->nullable();
            $table->timestamps();

            $table->index('team_id');
        });

        // Pivot: which users are assigned to a workspace, and their workspace role.
        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // member|approver|client
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('workspaces');
    }
};
