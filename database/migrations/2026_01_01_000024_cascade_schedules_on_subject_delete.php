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
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
        });
    }
};
