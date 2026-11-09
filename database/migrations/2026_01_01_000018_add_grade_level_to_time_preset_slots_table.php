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
        Schema::table('time_preset_slots', function (Blueprint $table) {
            $table->foreignId('grade_level_id')
                ->nullable()
                ->after('time_preset_id')
                ->constrained('grade_levels')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_preset_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_level_id');
        });
    }
};
