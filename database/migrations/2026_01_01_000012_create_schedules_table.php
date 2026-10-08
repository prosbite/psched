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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_group_id')->constrained('schedule_groups')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('subject_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('proctor_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['schedule_group_id', 'section_id', 'start_time']);
            $table->index(['schedule_group_id', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
