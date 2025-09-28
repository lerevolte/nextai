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
        Schema::create('trigger_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trigger_id')
                ->constrained('function_triggers')
                ->onDelete('cascade');
            $table->enum('type', [
                'contains',
                'equals',
                'regex',
                'intent',
                'entity_detected',
                'starts_with',
                'ends_with',
                'greater_than',
                'less_than'
            ])->default('contains');
            $table->enum('field', [
                'message',
                'user_name',
                'user_email',
                'user_phone',
                'context_var',
                'conversation_status',
                'messages_count',
                'time',
                'day_of_week',
                'metadata'
            ])->default('message');
            $table->enum('operator', [
                'equals',
                'not_equals',
                'contains',
                'not_contains',
                'greater',
                'greater_or_equal',
                'less',
                'less_or_equal',
                'matches',
                'in',
                'not_in',
                'starts_with',
                'ends_with',
                'is_null',
                'is_not_null'
            ])->default('contains');
            $table->json('value')->nullable()->comment('Значение для сравнения');
            $table->enum('logic_operator', ['AND', 'OR'])->default('AND')->comment('Логический оператор для объединения условий');
            $table->integer('position')->default(0);
            $table->timestamps();
            
            // Индексы
            $table->index(['trigger_id', 'position']);
            $table->index('type');
            $table->index('field');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trigger_conditions');
    }
};