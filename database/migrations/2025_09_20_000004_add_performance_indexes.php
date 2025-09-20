<?
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerformanceIndexes extends Migration
{
    public function up()
    {
        // Добавляем составные индексы для оптимизации
        Schema::table('conversations', function (Blueprint $table) {
            $table->index(['bot_id', 'status', 'created_at'], 'idx_bot_status_date');
            $table->index(['bot_id', 'last_message_at'], 'idx_bot_last_message');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'role', 'created_at'], 'idx_conv_role_date');
            $table->index(['response_time'], 'idx_response_time');
        });

        Schema::table('knowledge_items', function (Blueprint $table) {
            $table->index(['knowledge_base_id', 'is_active', 'updated_at'], 'idx_kb_active_updated');
        });

        // Добавляем партиционирование для больших таблиц
        DB::statement("
            ALTER TABLE messages PARTITION BY RANGE (TO_DAYS(created_at)) (
                PARTITION p0 VALUES LESS THAN (TO_DAYS('2025-01-01')),
                PARTITION p1 VALUES LESS THAN (TO_DAYS('2025-02-01')),
                PARTITION p2 VALUES LESS THAN (TO_DAYS('2025-03-01')),
                PARTITION p3 VALUES LESS THAN (TO_DAYS('2025-04-01')),
                PARTITION p4 VALUES LESS THAN (TO_DAYS('2025-05-01')),
                PARTITION p5 VALUES LESS THAN (TO_DAYS('2025-06-01')),
                PARTITION p6 VALUES LESS THAN (TO_DAYS('2025-07-01')),
                PARTITION p7 VALUES LESS THAN (TO_DAYS('2025-08-01')),
                PARTITION p8 VALUES LESS THAN (TO_DAYS('2025-09-01')),
                PARTITION p9 VALUES LESS THAN (TO_DAYS('2025-10-01')),
                PARTITION p10 VALUES LESS THAN (TO_DAYS('2025-11-01')),
                PARTITION p11 VALUES LESS THAN (TO_DAYS('2025-12-01')),
                PARTITION p12 VALUES LESS THAN MAXVALUE
            )
        ");
    }

    public function down()
    {
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

        // Удаляем партиционирование
        DB::statement("ALTER TABLE messages REMOVE PARTITIONING");
    }
}