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
        Schema::create('bot_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')
                ->constrained('bots')
                ->onDelete('cascade');
            $table->string('name', 50)->comment('Уникальное имя сущности');
            $table->string('display_name');
            $table->enum('type', [
                'system',    // Системная сущность (email, phone, date, etc)
                'list',      // Список значений
                'regex',     // Регулярное выражение
                'composite'  // Составная сущность
            ])->default('list');
            $table->json('values')->nullable()->comment('Возможные значения или паттерны');
            $table->json('synonyms')->nullable()->comment('Синонимы для значений');
            $table->string('regex_pattern')->nullable()->comment('Паттерн для regex типа');
            $table->boolean('is_fuzzy')->default(false)->comment('Нечеткое соответствие');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Индексы
            $table->unique(['bot_id', 'name']);
            $table->index(['bot_id', 'type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_entities');
    }
};