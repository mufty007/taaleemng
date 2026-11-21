<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly staff payroll: salary details on each employee, a run per month, and
 * a line (payslip) per person with amounts snapped at prepare time.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->unsignedBigInteger('basic_salary')->nullable()->after('photo_path');
            $table->unsignedBigInteger('allowances')->default(0)->after('basic_salary');
            $table->unsignedBigInteger('deductions')->default(0)->after('allowances');
            $table->string('bank_name', 80)->nullable()->after('deductions');
            $table->string('bank_account_name', 120)->nullable()->after('bank_name');
            $table->string('bank_account_number', 20)->nullable()->after('bank_account_name');
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->ulid()->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('prepared');
            $table->unsignedBigInteger('gross_total')->default(0);
            $table->unsignedBigInteger('deductions_total')->default(0);
            $table->unsignedBigInteger('net_total')->default(0);
            $table->unsignedSmallInteger('headcount')->default(0);
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'year', 'month']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->ulid()->unique();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('staff_name', 160);
            $table->string('staff_no', 40);
            $table->string('designation', 80);
            $table->unsignedBigInteger('basic_salary');
            $table->unsignedBigInteger('allowances')->default(0);
            $table->unsignedBigInteger('deductions')->default(0);
            $table->unsignedBigInteger('net_pay');
            $table->string('bank_name', 80)->nullable();
            $table->string('bank_account_name', 120)->nullable();
            $table->string('bank_account_number', 20)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'staff_id']);
            $table->index(['school_id', 'payroll_run_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn([
                'basic_salary', 'allowances', 'deductions',
                'bank_name', 'bank_account_name', 'bank_account_number',
            ]);
        });
    }
};
