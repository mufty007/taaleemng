<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passport photos for ID cards, and files on assignments: the teacher's worksheet
 * and the student's uploaded paper (a PDF, a Word file or photos of written work).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('medical_notes');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('qualification');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->json('files')->nullable()->after('instructions');
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
            $table->json('files')->nullable()->after('body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn('files');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('files');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
