<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('type')->default('regular')->after('grade_level_id');
        });

        DB::table('subjects')->where('is_elective', true)->update(['type' => 'elective']);

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('is_elective');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->boolean('is_elective')->default(false)->after('grade_level_id');
        });

        DB::table('subjects')->where('type', 'elective')->update(['is_elective' => true]);

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
