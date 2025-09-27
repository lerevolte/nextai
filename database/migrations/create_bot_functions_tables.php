<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Основная таблица функций
        Schema::create('bot_functions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->string('name', 50)->comment('snake_case name');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('trigger_type', ['auto', 'manual', 'keyword'])->default('auto');
            $table->json('trigger_keywords')->nullable();
            $table->timestamps();
            
            $table->unique(['bot_id', 'name']);
            $table->index('is_active');
        });
        
        // Параметры функции
        Schema::create('function_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('function_id')->constrained('bot_functions')->onDelete('cascade');
            $table->string('code', 50);
            $table->string('name');
            $table->enum('type', ['string', 'number', 'boolean', 'date']);
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->json('validation_rules')->nullable();
            $table->json('extraction_hints')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
            
            $table->unique(['function_id', 'code']);
            $table->index('position');
        });
        
        // Действия функции
        Schema::create('function_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('function_id')->constrained('bot_functions')->onDelete('cascade');
            $table->string('type', 50); // create_lead, send_email, webhook
            $table->string('provider', 50); // bitrix24, amocrm, custom
            $table->json('config'); // Конфигурация действия
            $table->json('field_mapping')->nullable(); // Маппинг полей
            $table->integer('position')->default(0);
            $table->timestamps();
            
            $table->index(['type', 'provider']);
            $table->index('position');
        });
        
        // Поведение после выполнения
        Schema::create('function_behaviors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('function_id')->constrained('bot_functions')->onDelete('cascade');
            $table->enum('on_success', ['continue', 'pause', 'enhance_prompt']);
            $table->enum('on_error', ['continue', 'pause', 'notify']);
            $table->text('success_message')->nullable();
            $table->text('error_message')->nullable();
            $table->text('prompt_enhancement')->nullable();
            $table->timestamps();
            
            $table->unique('function_id');
        });
        
        // Логи выполнения
        Schema::create('function_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('function_id')->constrained('bot_functions')->onDelete('cascade');
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('message_id')->nullable()->constrained()->onDelete('set null');
            $table->json('extracted_params')->nullable();
            $table->json('action_results')->nullable();
            $table->enum('status', ['pending', 'success', 'failed']);
            $table->text('error_message')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            
            $table->index(['function_id', 'status']);
            $table->index(['conversation_id', 'created_at']);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('function_executions');
        Schema::dropIfExists('function_behaviors');
        Schema::dropIfExists('function_actions');
        Schema::dropIfExists('function_parameters');
        Schema::dropIfExists('bot_functions');
    }
};