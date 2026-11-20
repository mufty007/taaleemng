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
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('name');
            $table->string('slug', 60)->unique();
            $table->string('short_code', 10)->unique();
            $table->string('logo_path')->nullable();
            $table->string('motto')->nullable();
            $table->string('address')->nullable();
            $table->string('state', 40)->nullable();
            $table->string('lga', 80)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('school_type', 30);
            $table->string('curriculum_type', 30)->default('nigerian');
            $table->string('status', 20)->default('trial')->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('school_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'key']);
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('pattern', 100);
            $table->unsignedInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('school_settings');
        Schema::dropIfExists('school_user');
        Schema::dropIfExists('schools');
    }
};
