<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fee items can be paused: they stay on old invoices but are left off new ones.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fee_items', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_optional');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_items', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
