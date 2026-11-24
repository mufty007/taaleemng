<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A school can choose a plan and pay for it itself; the invoice remembers what
 * was bought so the plan switches over once Paystack confirms the payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_invoices', function (Blueprint $table) {
            $table->string('plan', 20)->nullable()->after('billing_model');
            $table->string('billing_cycle', 20)->nullable()->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('school_invoices', function (Blueprint $table) {
            $table->dropColumn(['plan', 'billing_cycle']);
        });
    }
};
