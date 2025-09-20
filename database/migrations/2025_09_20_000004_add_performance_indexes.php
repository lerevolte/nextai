<?

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddPerformanceIndexes extends Migration
{
    /**
     * Проверка существования индекса
     */
    protected function indexExists($table, $name)
    {
        $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);
        return count($result) > 0;
    }

    public function up()
    {
        // Добавляем составные индексы для оптимизации
        // Используем try-catch для безопасного добавления индексов
        
        Schema::table('conversations', function (Blueprint $table) {
            try {
                if (!$this->indexExists('conversations', 'idx_bot_status_date')) {
                    $table->index(['bot_id', 'status', 'created_at'], 'idx_bot_status_date');
                }
            } catch (\Exception $e) {
                // Индекс уже существует
            }
            
            try {
                if (!$this->indexExists('conversations', 'idx_bot_last_message')) {
                    $table->index(['bot_id', 'last_message_at'], 'idx_bot_last_message');
                }
            } catch (\Exception $e) {
                // Индекс уже существует
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            try {
                if (!$this->indexExists('messages', 'idx_conv_role_date')) {
                    $table->index(['conversation_id', 'role', 'created_at'], 'idx_conv_role_date');
                }
            } catch (\Exception $e) {
                // Индекс уже существует
            }
            
            try {
                if (!$this->indexExists('messages', 'idx_response_time')) {
                    $table->index(['response_time'], 'idx_response_time');
                }
            } catch (\Exception $e) {
                // Индекс уже существует
            }
        });

        Schema::table('knowledge_items', function (Blueprint $table) {
            try {
                if (!$this->indexExists('knowledge_items', 'idx_kb_active_updated')) {
                    $table->index(['knowledge_base_id', 'is_active', 'updated_at'], 'idx_kb_active_updated');
                }
            } catch (\Exception $e) {
                // Индекс уже существует
            }
        });

        // Вместо партиционирования, создаем архивные таблицы для старых данных
        $this->createArchiveTables();
        
        // Создаем процедуру для архивации старых сообщений
        $this->createArchiveProcedure();
    }

    public function down()
    {
        // Удаляем индексы
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('idx_bot_status_date');
            $table->dropIndex('idx_bot_last_message');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_conv_role_date');
            $table->dropIndex('idx_response_time');
        });

        Schema::table('knowledge_items', function (Blueprint $table) {
            $table->dropIndex('idx_kb_active_updated');
        });

        // Удаляем архивные таблицы
        Schema::dropIfExists('messages_archive');
        Schema::dropIfExists('conversations_archive');
        
        // Удаляем процедуру архивации
        DB::unprepared('DROP PROCEDURE IF EXISTS archive_old_messages');
    }

    /**
     * Создание архивных таблиц для старых данных
     */
    protected function createArchiveTables()
    {
        // Архивная таблица для сообщений
        if (!Schema::hasTable('messages_archive')) {
            Schema::create('messages_archive', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('original_id');
                $table->unsignedBigInteger('conversation_id');
                $table->string('role');
                $table->text('content');
                $table->json('attachments')->nullable();
                $table->integer('tokens_used')->nullable();
                $table->float('response_time')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('original_created_at');
                $table->timestamp('archived_at')->useCurrent();
                
                $table->index(['conversation_id', 'original_created_at']);
                $table->index('archived_at');
            });
        }

        // Архивная таблица для диалогов
        if (!Schema::hasTable('conversations_archive')) {
            Schema::create('conversations_archive', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('original_id');
                $table->unsignedBigInteger('bot_id');
                $table->unsignedBigInteger('channel_id');
                $table->string('external_id')->nullable();
                $table->string('status');
                $table->string('user_name')->nullable();
                $table->string('user_email')->nullable();
                $table->string('user_phone')->nullable();
                $table->json('user_data')->nullable();
                $table->integer('messages_count')->default(0);
                $table->integer('ai_tokens_used')->default(0);
                $table->timestamp('last_message_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->json('context')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('original_created_at');
                $table->timestamp('archived_at')->useCurrent();
                
                $table->index(['bot_id', 'original_created_at']);
                $table->index('archived_at');
            });
        }
    }

    /**
     * Создание хранимой процедуры для архивации старых данных
     */
    protected function createArchiveProcedure()
    {
        $procedure = "
        CREATE PROCEDURE IF NOT EXISTS archive_old_messages(IN days_to_keep INT)
        BEGIN
            DECLARE archived_count INT DEFAULT 0;
            
            -- Начинаем транзакцию
            START TRANSACTION;
            
            -- Архивируем старые сообщения
            INSERT INTO messages_archive (
                original_id, conversation_id, role, content, 
                attachments, tokens_used, response_time, metadata, 
                original_created_at
            )
            SELECT 
                id, conversation_id, role, content,
                attachments, tokens_used, response_time, metadata,
                created_at
            FROM messages
            WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY)
            AND conversation_id IN (
                SELECT id FROM conversations 
                WHERE status = 'closed' 
                AND closed_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY)
            );
            
            SET archived_count = ROW_COUNT();
            
            -- Удаляем архивированные сообщения
            DELETE FROM messages 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY)
            AND conversation_id IN (
                SELECT id FROM conversations 
                WHERE status = 'closed' 
                AND closed_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY)
            );
            
            -- Фиксируем транзакцию
            COMMIT;
            
            -- Возвращаем количество архивированных записей
            SELECT archived_count AS messages_archived;
        END;
        ";

        DB::unprepared('DROP PROCEDURE IF EXISTS archive_old_messages');
        DB::unprepared($procedure);
    }
}