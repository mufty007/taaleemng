<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money going out: diesel, repairs, salaries, exam fees… with a photo of the receipt.
 * Mistakes are voided with a reason rather than deleted, so the books stay complete.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->ulid()->unique();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 30);
            $table->string('description', 150);
            $table->unsignedBigInteger('amount');
            $table->date('spent_on');
            $table->string('paid_to', 120)->nullable();
            $table->string('method', 20);
            $table->string('reference', 60)->nullable();
            $table->json('receipt')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'spent_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
