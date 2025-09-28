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
        Schema::create('function_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trigger_id')
                ->constrained('function_triggers')
                ->onDelete('cascade');
            $table->string('cron_expression')->nullable()->comment('Cron выражение для расписания');
            $table->time('time')->nullable()->comment('Время выполнения');
            $table->json('days_of_week')->nullable()->comment('Дни недели [1-7]');
            $table->json('days_of_month')->nullable()->comment('Дни месяца [1-31]');
            $table->json('months')->nullable()->comment('Месяцы [1-12]');
            $table->string('timezone')->default('UTC');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Индексы
            $table->index(['trigger_id', 'is_active']);
            $table->index('next_run_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('function_schedules');
    }
};