<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: the school calendar and class structure.
 *
 * Staff records live here too (not with students) because class arms and
 * class subjects point at their form teacher and subject teacher.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('name', 30);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('mid_term_starts_on')->nullable();
            $table->date('mid_term_ends_on')->nullable();
            $table->date('next_term_begins_on')->nullable();
            $table->string('status', 20)->default('open');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['academic_session_id', 'sequence']);
            $table->index(['school_id', 'is_current']);
        });

        Schema::create('class_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('short_name', 12);
            $table->string('section', 20);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('code', 12);
            $table->string('category', 20)->default('core');
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'code']);
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('staff_no', 40);
            $table->string('title', 20)->nullable();
            $table->string('first_name', 60);
            $table->string('last_name', 60);
            $table->string('other_names', 60)->nullable();
            $table->string('gender', 10);
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('designation', 80);
            $table->string('department', 80)->nullable();
            $table->string('employment_type', 20)->default('full_time');
            $table->date('date_employed')->nullable();
            $table->string('qualification', 120)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'staff_no']);
            $table->unique(['school_id', 'user_id']);
        });

        Schema::create('class_arms', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stream_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('form_teacher_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('name', 20);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamps();

            $table->unique(['class_level_id', 'name']);
        });

        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_arm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->boolean('is_compulsory')->default(true);
            $table->timestamps();

            $table->unique(['class_arm_id', 'subject_id']);
            $table->index(['school_id', 'teacher_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('class_arms');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('streams');
        Schema::dropIfExists('class_levels');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_sessions');
    }
};
