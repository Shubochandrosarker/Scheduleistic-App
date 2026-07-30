<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational surfaces: per-user notification preferences, the channel health
 * timeline, signed outbound webhooks and their delivery log, and the audit log.
 *
 * `channel_health_events` and `audit_logs` are append-only by convention —
 * neither has an `updated_at` that the app writes after insert, and nothing in
 * the codebase updates a row once it exists. That is what makes them usable as
 * evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event');                       // e.g. publish_failed
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(true);
            $table->boolean('push')->default(false);
            $table->string('digest')->default('immediate'); // immediate|daily|weekly|off
            $table->timestamps();

            $table->unique(['user_id', 'event']);
        });

        Schema::create('channel_health_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            // connected|token_expiring|token_expired|permission_missing
            // |provider_unavailable|rate_limited|publish_failed|reconnected
            $table->string('state');
            $table->string('severity')->default('info');   // info|warning|critical
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['channel_id', 'detected_at']);
            $table->index(['channel_id', 'resolved_at']);
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->string('health_state')->default('connected')->after('status');
            $table->timestamp('last_published_at')->nullable()->after('health_state');
            $table->timestamp('last_metrics_sync_at')->nullable()->after('last_published_at');
            $table->timestamp('last_health_check_at')->nullable()->after('last_metrics_sync_at');
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 2048);
            // Encrypted at rest; used to sign every payload with HMAC-SHA256.
            $table->text('secret');
            $table->timestamp('secret_rotated_at')->nullable();
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->string('disabled_reason')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'is_active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->uuid('event_id');                       // idempotency key sent to the receiver
            $table->string('event');
            $table->json('payload');
            $table->string('status')->default('pending');   // pending|delivered|failed
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'status']);
            $table->index('event_id');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('context')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['team_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');

        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn([
                'health_state',
                'last_published_at',
                'last_metrics_sync_at',
                'last_health_check_at',
            ]);
        });

        Schema::dropIfExists('channel_health_events');
        Schema::dropIfExists('notification_preferences');
    }
};
