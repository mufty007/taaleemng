<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: admissions. A family applies (online or at the office), the school
 * screens, books an entrance exam, offers a place, and enrols the child.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->ulid()->unique();
            $table->string('application_no', 30);
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_level_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('source', 10);
            $table->string('first_name', 60);
            $table->string('middle_name', 60)->nullable();
            $table->string('last_name', 60);
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->string('state_of_origin', 40)->nullable();
            $table->string('religion', 20)->nullable();
            $table->string('previous_school', 120)->nullable();
            $table->string('address')->nullable();
            $table->string('guardian_title', 20)->nullable();
            $table->string('guardian_first_name', 60);
            $table->string('guardian_last_name', 60);
            $table->string('guardian_relationship', 20);
            $table->string('guardian_phone', 20);
            $table->string('guardian_email', 160)->nullable();
            $table->string('guardian_occupation', 80)->nullable();
            $table->dateTime('exam_at')->nullable();
            $table->decimal('exam_score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'application_no']);
            $table->index(['school_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
