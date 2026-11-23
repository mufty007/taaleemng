<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each school pays Ta'aleem (plan, price, invoices), which modules it has, and
 * notices super admins broadcast to schools.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('plan', 20)->default('standard')->after('trial_ends_at');
            $table->unsignedBigInteger('price_per_student')->nullable()->after('plan');
            $table->string('billing_cycle', 10)->default('termly')->after('price_per_student');
            $table->date('paid_until')->nullable()->after('billing_cycle');
            $table->json('disabled_modules')->nullable()->after('paid_until');
            $table->text('billing_notes')->nullable()->after('disabled_modules');
        });

        Schema::create('school_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->ulid()->unique();
            $table->string('number', 30)->unique();
            $table->string('period', 80);
            $table->unsignedInteger('students');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('amount');
            $table->string('status', 10)->default('draft');
            $table->date('issued_on');
            $table->date('due_on');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference', 80)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_on']);
        });

        Schema::create('platform_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('title', 120);
            $table->text('body');
            $table->string('level', 10)->default('info');
            $table->string('audience', 10)->default('admins');
            $table->json('school_ids')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('email_admins')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('platform_broadcast_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['platform_broadcast_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_broadcast_user');
        Schema::dropIfExists('platform_broadcasts');
        Schema::dropIfExists('school_invoices');
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['plan', 'price_per_student', 'billing_cycle', 'paid_until', 'disabled_modules', 'billing_notes']);
        });
    }
};
