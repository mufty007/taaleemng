<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: students, their guardians, and which class each student is in per session.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admission_no', 40);
            $table->string('first_name', 60);
            $table->string('middle_name', 60)->nullable();
            $table->string('last_name', 60);
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->string('state_of_origin', 40)->nullable();
            $table->string('lga', 80)->nullable();
            $table->string('religion', 20)->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('genotype', 5)->nullable();
            $table->string('address')->nullable();
            $table->string('previous_school', 120)->nullable();
            $table->text('medical_notes')->nullable();
            $table->date('admitted_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'admission_no']);
            $table->index(['school_id', 'last_name', 'first_name']);
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 20)->nullable();
            $table->string('first_name', 60);
            $table->string('last_name', 60);
            $table->string('relationship', 20);
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('occupation', 80)->nullable();
            $table->string('address')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'phone']);
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['guardian_id', 'student_id']);
        });

        Schema::create('enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_arm_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id']);
            $table->index(['school_id', 'academic_session_id', 'class_arm_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrolments');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
    }
};
