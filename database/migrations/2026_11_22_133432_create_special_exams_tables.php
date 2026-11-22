<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Special exams: mock exams and WAEC, NECO, JAMB, BECE and Common Entrance practice,
 * kept apart from termly results. Each has papers, candidates and a score per paper.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('special_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->ulid()->unique();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('kind', 20);
            $table->date('held_on');
            $table->unsignedTinyInteger('pass_mark')->default(50);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'held_on']);
        });

        Schema::create('special_exam_papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('special_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('max_score')->default(100);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['special_exam_id', 'name']);
        });

        Schema::create('special_exam_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('special_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_arm_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['special_exam_id', 'student_id']);
        });

        Schema::create('special_exam_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('special_exam_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('special_exam_paper_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 1);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['special_exam_candidate_id', 'special_exam_paper_id'], 'special_exam_scores_unique_entry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_exam_scores');
        Schema::dropIfExists('special_exam_candidates');
        Schema::dropIfExists('special_exam_papers');
        Schema::dropIfExists('special_exams');
    }
};
