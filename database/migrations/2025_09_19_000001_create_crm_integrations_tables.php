<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Таблица CRM интеграций
        Schema::create('crm_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('type'); // bitrix24, amocrm, avito
            $table->string('name');
            $table->json('credentials'); // Зашифрованные данные доступа
            $table->json('settings')->nullable(); // Настройки интеграции
            $table->json('field_mapping')->nullable(); // Маппинг полей
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->json('sync_status')->nullable();
            $table->timestamps();
            
            $table->unique(['organization_id', 'type']);
            $table->index(['organization_id', 'is_active']);
        });

        // Связь ботов с CRM
        Schema::create('bot_crm_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->foreignId('crm_integration_id')->constrained()->onDelete('cascade');
            $table->json('settings')->nullable(); // Настройки для конкретного бота
            $table->boolean('sync_contacts')->default(true);
            $table->boolean('sync_conversations')->default(true);
            $table->boolean('create_leads')->default(true);
            $table->boolean('create_deals')->default(false);
            $table->string('lead_source')->nullable(); // Источник лидов
            $table->string('responsible_user_id')->nullable(); // Ответственный в CRM
            $table->json('pipeline_settings')->nullable(); // Настройки воронки
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['bot_id', 'crm_integration_id']);
        });

        // Таблица синхронизации сущностей
        Schema::create('crm_sync_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_integration_id')->constrained()->onDelete('cascade');
            $table->string('entity_type'); // contact, lead, deal, conversation
            $table->string('local_id'); // ID в нашей системе
            $table->string('remote_id'); // ID в CRM
            $table->json('remote_data')->nullable(); // Кэш данных из CRM
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sync_metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['crm_integration_id', 'entity_type', 'local_id']);
            $table->index(['crm_integration_id', 'entity_type', 'remote_id']);
        });

        // Логи синхронизации
        Schema::create('crm_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_integration_id')->constrained()->onDelete('cascade');
            $table->string('direction'); // incoming, outgoing
            $table->string('entity_type');
            $table->string('action'); // create, update, delete
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->string('status'); // success, error
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['crm_integration_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        // Webhook эндпоинты
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('url')->unique();
            $table->string('secret_key');
            $table->string('target_type'); // bot, crm_integration, custom
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('event_types'); // Типы событий для отправки
            $table->json('headers')->nullable(); // Дополнительные заголовки
            $table->boolean('is_active')->default(true);
            $table->integer('retry_count')->default(3);
            $table->integer('timeout')->default(30); // секунды
            $table->timestamps();
            
            $table->index(['organization_id', 'is_active']);
            $table->index(['target_type', 'target_id']);
        });

        // История вебхуков
        Schema::create('webhook_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->onDelete('cascade');
            $table->string('event_type');
            $table->json('payload');
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->string('status'); // pending, success, failed
            $table->integer('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            
            $table->index(['webhook_endpoint_id', 'status']);
            $table->index(['status', 'next_retry_at']);
        });

        // Добавляем поля в таблицу conversations для CRM
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('crm_lead_id')->nullable()->after('closed_at');
            $table->string('crm_contact_id')->nullable()->after('crm_lead_id');
            $table->string('crm_deal_id')->nullable()->after('crm_contact_id');
            $table->json('crm_data')->nullable()->after('crm_deal_id');
            
            $table->index('crm_lead_id');
            $table->index('crm_contact_id');
            $table->index('crm_deal_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['crm_lead_id', 'crm_contact_id', 'crm_deal_id', 'crm_data']);
        });
        
        Schema::dropIfExists('webhook_calls');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('crm_sync_logs');
        Schema::dropIfExists('crm_sync_entities');
        Schema::dropIfExists('bot_crm_integrations');
        Schema::dropIfExists('crm_integrations');
    }
};