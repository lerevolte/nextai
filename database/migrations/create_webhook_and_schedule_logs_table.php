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
        // Логи webhook запросов
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('function_id')
                ->constrained('bot_functions')
                ->onDelete('cascade');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('method', 10);
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->integer('response_code')->nullable();
            $table->json('response_body')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();
            
            $table->index(['function_id', 'created_at']);
            $table->index('ip_address');
        });
        
        // Добавляем поле error_count в function_schedules
        Schema::table('function_schedules', function (Blueprint $table) {
            $table->integer('error_count')->default(0)->after('is_active');
            $table->json('last_error')->nullable()->after('error_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        
        Schema::table('function_schedules', function (Blueprint $table) {
            $table->dropColumn(['error_count', 'last_error']);
        });
    }
};