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
        // Добавляем поле metadata в таблицу bots если его нет
        if (!Schema::hasColumn('bots', 'metadata')) {
            Schema::table('bots', function (Blueprint $table) {
                $table->json('metadata')->nullable()->after('settings');
            });
        }
        
        // Добавляем поле metadata в таблицу messages если его нет
        if (!Schema::hasColumn('messages', 'metadata')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->json('metadata')->nullable()->after('response_time');
            });
        }
        
        // Добавляем поля для хранения ID сообщений из Битрикс24
        if (!Schema::hasColumn('conversations', 'bitrix24_chat_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('bitrix24_chat_id')->nullable()->after('metadata');
                $table->index('bitrix24_chat_id');
            });
        }
        
        // Обновляем таблицу bot_crm_integrations для хранения настроек коннектора
        Schema::table('bot_crm_integrations', function (Blueprint $table) {
            if (!Schema::hasColumn('bot_crm_integrations', 'connector_settings')) {
                $table->json('connector_settings')->nullable()->after('pipeline_settings');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            if (Schema::hasColumn('bots', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
        
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
        
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'bitrix24_chat_id')) {
                $table->dropColumn('bitrix24_chat_id');
            }
        });
        
        Schema::table('bot_crm_integrations', function (Blueprint $table) {
            if (Schema::hasColumn('bot_crm_integrations', 'connector_settings')) {
                $table->dropColumn('connector_settings');
            }
        });
    }
};