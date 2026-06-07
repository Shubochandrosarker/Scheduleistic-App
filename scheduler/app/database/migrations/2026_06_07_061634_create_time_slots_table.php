<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posting-time templates per workspace. "Add to queue" fills the next free
 * slot from these, so users don't have to pick an exact time every post.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 (Sun) - 6 (Sat)
            $table->string('time', 5);                  // "HH:MM" in the workspace timezone
            $table->timestamps();

            $table->unique(['workspace_id', 'day_of_week', 'time'], 'time_slots_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};
