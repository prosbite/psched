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
        Schema::table('schedule_groups', function (Blueprint $table) {
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignId('quarter_id')->constrained('quarters')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_year_id');
            $table->dropConstrainedForeignId('quarter_id');
        });
    }
};
