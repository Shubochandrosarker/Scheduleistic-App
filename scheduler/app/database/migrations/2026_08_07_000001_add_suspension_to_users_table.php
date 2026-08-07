<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-level suspension, distinct from `teams.suspended_at` (an entire
 * organization). A platform admin can suspend a single account — e.g. for
 * abuse — without touching the organizations they belong to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('is_platform_admin');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
            $table->foreignId('suspended_by')->nullable()->after('suspension_reason')
                ->constrained('users')->nullOnDelete();

            $table->index('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // SQLite rebuilds the table to drop a column, and chokes if an
            // index on that column is still there when it does — drop the
            // index explicitly first rather than relying on the column drop
            // to take it with it.
            $table->dropIndex(['suspended_at']);
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};
