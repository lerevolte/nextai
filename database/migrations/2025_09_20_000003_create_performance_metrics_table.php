<?
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePerformanceMetricsTable extends Migration
{
    public function up()
    {
        // Метрики производительности
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type'); // cache_hit, api_response, db_query, etc.
            $table->string('metric_name');
            $table->float('value');
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            
            $table->index(['metric_type', 'recorded_at']);
            $table->index('recorded_at');
        });

        // Кэш частых запросов
        Schema::create('cached_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->string('question_hash')->index();
            $table->text('question');
            $table->text('response');
            $table->integer('hit_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->unique(['bot_id', 'question_hash']);
            $table->index(['bot_id', 'hit_count']);
            $table->index('expires_at');
        });

        // Оптимизация промптов
        Schema::create('prompt_optimizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->text('original_prompt');
            $table->text('optimized_prompt');
            $table->integer('original_tokens');
            $table->integer('optimized_tokens');
            $table->float('token_reduction_percentage');
            $table->json('optimization_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['bot_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('prompt_optimizations');
        Schema::dropIfExists('cached_responses');
        Schema::dropIfExists('performance_metrics');
    }
}