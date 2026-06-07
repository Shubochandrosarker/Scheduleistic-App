<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // "none" | "daily" | "weekly" | "monthly" — drives next-occurrence creation.
            $table->string('recurring_rule')->nullable()->after('timezone');
            $table->foreignId('parent_post_id')->nullable()->after('recurring_rule')
                ->constrained('posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_post_id');
            $table->dropColumn('recurring_rule');
        });
    }
};
