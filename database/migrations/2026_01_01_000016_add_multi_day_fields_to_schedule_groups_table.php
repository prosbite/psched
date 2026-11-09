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
            $table->unsignedTinyInteger('days')->default(3)->after('status');
            $table->unsignedTinyInteger('exams_per_day')->default(3)->after('days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_groups', function (Blueprint $table) {
            $table->dropColumn(['days', 'exams_per_day']);
        });
    }
};
