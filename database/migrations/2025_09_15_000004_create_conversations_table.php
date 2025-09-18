<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->string('external_id')->nullable(); // ID в мессенджере
            $table->string('status')->default('active'); // active, waiting_operator, closed
            
            // Данные пользователя
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_phone')->nullable();
            $table->json('user_data')->nullable();
            
            // Метрики
            $table->integer('messages_count')->default(0);
            $table->integer('ai_tokens_used')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            
            $table->json('context')->nullable(); // Контекст разговора
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            $table->index(['bot_id', 'status']);
            $table->index(['channel_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};