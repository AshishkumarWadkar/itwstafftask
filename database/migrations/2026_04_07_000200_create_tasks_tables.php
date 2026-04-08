<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('repeat_type')->default('weekdays'); // weekdays|weekends|everyday|once
            $table->json('repeat_days')->nullable(); // ["Mon","Tue",...]
            $table->unsignedInteger('reminder_before_minutes')->default(0);
            $table->unsignedInteger('occurrences')->default(1);
            $table->boolean('capture_photo')->default(false);
            $table->boolean('notify_once_done')->default(false);
            $table->boolean('overdue_alarm_enabled')->default(false);
            $table->string('icon_name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('task_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->unsignedInteger('occurrence_minutes'); // 0..1439
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['task_id', 'user_id']);
        });

        Schema::create('task_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('day_key'); // ms start-of-day (same as app)
            $table->unsignedInteger('occurrence_minutes')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamps();
            $table->unique(['task_id', 'user_id', 'day_key', 'occurrence_minutes']);
        });

        Schema::create('user_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->string('name')->default('mobile');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tokens');
        Schema::dropIfExists('task_logs');
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('task_times');
        Schema::dropIfExists('tasks');
    }
};

