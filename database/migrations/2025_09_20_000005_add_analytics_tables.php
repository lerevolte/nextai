<?
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAnalyticsTables extends Migration
{
    public function up()
    {
        // Агрегированная статистика по часам
        Schema::create('hourly_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('bot_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamp('hour');
            $table->integer('conversations_started')->default(0);
            $table->integer('conversations_closed')->default(0);
            $table->integer('messages_sent')->default(0);
            $table->integer('unique_users')->default(0);
            $table->float('avg_response_time')->nullable();
            $table->float('avg_conversation_duration')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->json('channel_breakdown')->nullable();
            $table->json('additional_metrics')->nullable();
            $table->timestamps();
            
            $table->unique(['organization_id', 'bot_id', 'hour']);
            $table->index(['organization_id', 'hour']);
            $table->index(['bot_id', 'hour']);
        });

        // Пользовательские сегменты для аналитики
        Schema::create('user_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('criteria'); // Критерии сегментации
            $table->integer('user_count')->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();
            
            $table->index(['organization_id']);
        });

        // Воронки конверсии
        Schema::create('conversion_funnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->json('steps'); // Шаги воронки
            $table->json('metrics')->nullable(); // Метрики конверсии
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['organization_id', 'is_active']);
            $table->index(['bot_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('conversion_funnels');
        Schema::dropIfExists('user_segments');
        Schema::dropIfExists('hourly_stats');
    }
}