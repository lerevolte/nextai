<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Версии элементов базы знаний
        Schema::create('knowledge_item_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_item_id')->constrained()->onDelete('cascade');
            $table->integer('version')->default(1);
            $table->string('title');
            $table->longText('content');
            $table->json('embedding')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->text('change_notes')->nullable();
            $table->timestamps();
            
            $table->index(['knowledge_item_id', 'version']);
        });

        // Источники для автообновления
        Schema::create('knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_base_id')->constrained()->onDelete('cascade');
            $table->string('type'); // notion, url, google_drive, github
            $table->string('name');
            $table->json('config'); // Настройки подключения
            $table->json('sync_settings'); // Частота обновления, фильтры
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();
            $table->json('sync_status')->nullable(); // Статус и ошибки синхронизации
            $table->timestamps();
        });

        // Логи синхронизации
        Schema::create('knowledge_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_source_id')->constrained()->onDelete('cascade');
            $table->string('status'); // success, partial, failed
            $table->integer('items_added')->default(0);
            $table->integer('items_updated')->default(0);
            $table->integer('items_deleted')->default(0);
            $table->json('details')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Добавляем поля в knowledge_items
        Schema::table('knowledge_items', function (Blueprint $table) {
            $table->foreignId('knowledge_source_id')->nullable()->after('knowledge_base_id')->constrained();
            $table->string('external_id')->nullable()->after('source_url'); // ID во внешней системе
            $table->integer('version')->default(1)->after('is_active');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sync_metadata')->nullable(); // Метаданные синхронизации
            
            $table->index(['knowledge_source_id', 'external_id']);
        });

        // Векторная база данных (индексы для эмбеддингов)
        Schema::create('vector_indices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_base_id')->constrained()->onDelete('cascade');
            $table->string('provider')->default('local'); // local, pinecone, weaviate, qdrant
            $table->json('config')->nullable();
            $table->integer('dimensions')->default(1536); // Размерность векторов
            $table->integer('total_vectors')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('knowledge_base_id');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_items', function (Blueprint $table) {
            $table->dropForeign(['knowledge_source_id']);
            $table->dropColumn(['knowledge_source_id', 'external_id', 'version', 'last_synced_at', 'sync_metadata']);
        });
        
        Schema::dropIfExists('vector_indices');
        Schema::dropIfExists('knowledge_sync_logs');
        Schema::dropIfExists('knowledge_sources');
        Schema::dropIfExists('knowledge_item_versions');
    }
};