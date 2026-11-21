<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: how scores are made up (CA1, CA2, Exam), how totals become grades,
 * the scores themselves, and which class results have been released to families.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            $table->string('short_name', 10);
            $table->unsignedTinyInteger('max_score');
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('grading_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->json('bands');
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::table('class_levels', function (Blueprint $table) {
            $table->foreignId('grading_scheme_id')->nullable()->after('position')->constrained()->nullOnDelete();
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrolment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['term_id', 'enrolment_id', 'class_subject_id', 'assessment_component_id'], 'scores_unique_entry');
            $table->index(['school_id', 'term_id', 'class_subject_id']);
        });

        Schema::create('result_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_arm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['term_id', 'class_arm_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_publications');
        Schema::dropIfExists('scores');
        Schema::table('class_levels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grading_scheme_id');
        });
        Schema::dropIfExists('grading_schemes');
        Schema::dropIfExists('assessment_components');
    }
};
