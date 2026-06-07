<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization plan, white-label branding, custom domain, and suspension.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('plan')->default('free')->after('personal_team');
            $table->json('branding')->nullable()->after('plan');
            $table->string('custom_domain')->nullable()->unique()->after('branding');
            $table->timestamp('custom_domain_verified_at')->nullable()->after('custom_domain');
            $table->string('custom_domain_token')->nullable()->after('custom_domain_verified_at');
            $table->timestamp('suspended_at')->nullable()->after('custom_domain_token');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'plan',
                'branding',
                'custom_domain',
                'custom_domain_verified_at',
                'custom_domain_token',
                'suspended_at',
            ]);
        });
    }
};
