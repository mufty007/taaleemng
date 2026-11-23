<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seat-based subscription tiers, dual billing (flat vs per-student), class
 * structure preference, and customisable school setup / role onboarding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('billing_model', 20)->default('flat')->after('plan');
            $table->unsignedInteger('max_students')->nullable()->after('billing_notes');
            $table->unsignedInteger('max_staff')->nullable()->after('max_students');
            $table->string('class_structure', 20)->default('arms')->after('max_staff');
            $table->json('setup_checklist')->nullable()->after('class_structure');
            $table->json('role_onboarding')->nullable()->after('setup_checklist');
        });

        Schema::table('school_user', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('last_active_at');
        });

        Schema::table('school_invoices', function (Blueprint $table) {
            $table->string('billing_model', 20)->nullable()->after('period');
            $table->string('paystack_reference')->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('school_invoices', function (Blueprint $table) {
            $table->dropColumn(['billing_model', 'paystack_reference']);
        });

        Schema::table('school_user', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn([
                'billing_model', 'max_students', 'max_staff', 'class_structure',
                'setup_checklist', 'role_onboarding',
            ]);
        });
    }
};
