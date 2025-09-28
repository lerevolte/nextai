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
        Schema::create('trigger_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trigger_id')
                ->constrained('function_triggers')
                ->onDelete('cascade');
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->onDelete('set null');
            $table->foreignId('message_id')
                ->nullable()
                ->constrained('messages')
                ->onDelete('set null');
            $table->boolean('matched')->default(false)->comment('Триггер сработал');
            $table->json('match_details')->nullable()->comment('Детали совпадения');
            $table->float('confidence', 3, 2)->nullable()->comment('Уверенность при AI-триггерах');
            $table->json('extracted_data')->nullable()->comment('Извлеченные данные');
            $table->timestamp('triggered_at');
            $table->timestamps();
            
            // Индексы
            $table->index(['trigger_id', 'matched']);
            $table->index(['conversation_id', 'created_at']);
            $table->index('triggered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trigger_logs');
    }
};