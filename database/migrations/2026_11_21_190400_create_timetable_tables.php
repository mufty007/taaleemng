<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The timetable: the school's bell schedule (periods and breaks) and, for each
 * class, which subject is taught in each period of the week. The teacher comes
 * from the class subject, so a change of subject teacher moves their lessons too.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('kind', 20)->default('lesson');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['school_id', 'position']);
        });

        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_arm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day');
            $table->foreignId('class_subject_id')->constrained()->cascadeOnDelete();
            $table->string('room', 40)->nullable();
            $table->timestamps();

            $table->unique(['class_arm_id', 'day', 'period_id']);
            $table->index(['school_id', 'day', 'period_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('periods');
    }
};
