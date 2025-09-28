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
        Schema::create('function_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('function_id')
                ->constrained('bot_functions')
                ->onDelete('cascade');
            $table->enum('type', [
                'intent',
                'keyword', 
                'pattern',
                'entity',
                'schedule',
                'webhook',
                'sentiment',
                'condition'
            ])->default('keyword');
            $table->string('name');
            $table->json('conditions')->comment('Условия срабатывания триггера');
            $table->integer('priority')->default(50)->comment('Приоритет при множественном совпадении (0-100)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Индексы
            $table->index(['function_id', 'is_active']);
            $table->index(['type', 'is_active']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('function_triggers');
    }
};