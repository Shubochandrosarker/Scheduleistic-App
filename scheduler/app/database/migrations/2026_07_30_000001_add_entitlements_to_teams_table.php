<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization entitlement overrides.
 *
 * PlanService merges these over the plan definition, and overrides may only
 * raise a limit or grant a capability. That is how legacy customers keep an
 * entitlement their new plan tier would not otherwise include, and how
 * Enterprise accounts get bespoke limits without inventing a plan key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->json('entitlements')->nullable()->after('plan');
            $table->string('timezone')->default('UTC')->after('entitlements');
            $table->timestamp('onboarded_at')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['entitlements', 'timezone', 'onboarded_at']);
        });
    }
};
