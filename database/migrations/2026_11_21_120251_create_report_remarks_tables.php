<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: the rest of a Nigerian report card. Attendance, affective and
 * psychomotor ratings, the form teacher's and principal's comments, a comment
 * bank to draw them from, and the end-of-session promotion decision.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->unsignedSmallInteger('school_days')->nullable()->after('next_term_begins_on');
        });

        Schema::create('report_traits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('category', 20);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('term_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrolment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days_present')->nullable();
            $table->json('traits')->nullable();
            $table->string('teacher_comment', 500)->nullable();
            $table->string('principal_comment', 500)->nullable();
            $table->string('promotion_decision', 20)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['term_id', 'enrolment_id']);
            $table->index(['school_id', 'term_id']);
        });

        Schema::create('comment_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('audience', 20);
            $table->decimal('min_average', 5, 2);
            $table->decimal('max_average', 5, 2);
            $table->string('body', 300);
            $table->timestamps();

            $table->index(['school_id', 'audience']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_templates');
        Schema::dropIfExists('term_reports');
        Schema::dropIfExists('report_traits');
        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn('school_days');
        });
    }
};
