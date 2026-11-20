<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id');
            $table->string('ip_address', 45)->nullable()->after('properties');

            $table->index(['school_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'created_at']);
            $table->dropColumn(['school_id', 'ip_address']);
        });
    }
};
