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
        Schema::create('bot_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')
                ->constrained('bots')
                ->onDelete('cascade');
            $table->string('name', 50)->comment('Уникальное имя намерения (order_status, booking, etc)');
            $table->string('display_name')->comment('Отображаемое название');
            $table->text('description')->nullable()->comment('Описание намерения');
            $table->json('training_phrases')->nullable()->comment('Примеры фраз для обучения AI');
            $table->json('entities')->nullable()->comment('Сущности для извлечения');
            $table->float('confidence_threshold', 3, 2)->default(0.7)->comment('Минимальная уверенность для срабатывания');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Индексы
            $table->unique(['bot_id', 'name']);
            $table->index(['bot_id', 'is_active']);
            $table->index('confidence_threshold');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_intents');
    }
};