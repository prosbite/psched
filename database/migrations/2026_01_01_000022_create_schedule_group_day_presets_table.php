<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule_group_day_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_group_id')->constrained('schedule_groups')->cascadeOnDelete();
            $table->unsignedTinyInteger('day');
            $table->foreignId('time_preset_id')->nullable()->constrained('time_presets')->nullOnDelete();
            $table->timestamps();

            $table->unique(['schedule_group_id', 'day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_group_day_presets');
    }
};
