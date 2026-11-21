<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: school fees. What each class pays per term, the invoice each student
 * gets, and payments (cash, transfer, POS or Paystack) allocated to invoices.
 * Every amount is in kobo.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fee_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->boolean('is_optional')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['term_id', 'class_level_id', 'fee_item_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->ulid()->unique();
            $table->string('invoice_no', 30);
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrolment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('total');
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->string('status', 20);
            $table->date('due_on')->nullable();
            $table->timestamp('issued_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'invoice_no']);
            $table->unique(['term_id', 'enrolment_id']);
            $table->index(['school_id', 'term_id', 'status']);
            $table->index(['school_id', 'student_id']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 120);
            $table->bigInteger('amount');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->ulid()->unique();
            $table->string('receipt_no', 30)->nullable();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('status', 20);
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'receipt_no']);
            $table->unique(['school_id', 'method', 'reference']);
            $table->index(['school_id', 'status', 'paid_at']);
            $table->index(['school_id', 'student_id']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['payment_id', 'invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_items');
    }
};
