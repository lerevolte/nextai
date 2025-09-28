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
        Schema::create('intent_training_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intent_id')
                ->constrained('bot_intents')
                ->onDelete('cascade');
            $table->text('phrase')->comment('Обучающая фраза');
            $table->json('entities')->nullable()->comment('Размеченные сущности в фразе');
            $table->boolean('is_negative')->default(false)->comment('Негативный пример (не должен срабатывать)');
            $table->enum('source', ['manual', 'auto', 'user_feedback'])->default('manual');
            $table->integer('usage_count')->default(0)->comment('Сколько раз использовалась');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Индексы
            $table->index(['intent_id', 'is_active']);
            $table->index('source');
            $table->fullText('phrase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intent_training_data');
    }
};