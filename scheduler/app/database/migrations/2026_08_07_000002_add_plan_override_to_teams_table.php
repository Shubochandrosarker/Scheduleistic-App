<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A platform-admin plan override, layered on top of the Stripe-synchronized
 * base plan rather than replacing it.
 *
 * `teams.plan` remains the single source of truth `Team::syncPlanFromSubscription()`
 * reconciles from the live Cashier subscription — nothing here changes that.
 * `PlanService` resolves the *effective* plan as: a valid, non-expired
 * override wins; otherwise the base plan; an unknown plan key (in either
 * column) safely falls back to `free`. A sync from Stripe must never erase
 * these columns, and an expired override must stop affecting access on its
 * own, without depending on a scheduled cleanup job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('plan_override')->nullable()->after('plan');
            $table->timestamp('plan_override_expires_at')->nullable()->after('plan_override');
            $table->string('plan_override_reason')->nullable()->after('plan_override_expires_at');
            $table->foreignId('plan_override_set_by')->nullable()->after('plan_override_reason')
                ->constrained('users')->nullOnDelete();

            $table->index('plan_override_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // SQLite rebuilds the table to drop a column, and chokes if an
            // index on that column is still there when it does — drop the
            // index explicitly first rather than relying on the column drop
            // to take it with it.
            $table->dropIndex(['plan_override_expires_at']);
            $table->dropConstrainedForeignId('plan_override_set_by');
            $table->dropColumn(['plan_override', 'plan_override_expires_at', 'plan_override_reason']);
        });
    }
};
