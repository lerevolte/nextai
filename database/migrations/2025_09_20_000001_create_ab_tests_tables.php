<?php

// database/migrations/2025_09_20_000001_create_ab_tests_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAbTestsTables extends Migration
{
    public function up()
    {
        // Таблица A/B тестов
        Schema::create('ab_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // prompt, temperature, model, welcome_message, etc.
            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->integer('traffic_percentage')->default(100); // % трафика для теста
            $table->integer('min_sample_size')->default(100);
            $table->integer('confidence_level')->default(95);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('winner_variant_id')->nullable();
            $table->boolean('auto_apply_winner')->default(false);
            $table->json('settings')->nullable();
            $table->integer('priority')->default(0);
            $table->timestamps();
            
            $table->index(['organization_id', 'status']);
            $table->index(['bot_id', 'status']);
            $table->index(['starts_at', 'ends_at']);
        });

        // Варианты A/B тестов
        Schema::create('ab_test_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ab_test_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('config'); // Конфигурация варианта
            $table->float('traffic_allocation')->default(50); // % трафика для варианта
            $table->boolean('is_control')->default(false);
            $table->integer('conversions')->default(0);
            $table->integer('participants')->default(0);
            $table->float('conversion_rate')->default(0);
            $table->json('metrics')->nullable();
            $table->timestamps();
            
            $table->index(['ab_test_id', 'is_control']);
        });

        // Результаты A/B тестов
        Schema::create('ab_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ab_test_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->constrained('ab_test_variants')->onDelete('cascade');
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->json('metrics')->nullable(); // Метрики для этого результата
            $table->timestamps();
            
            $table->unique(['conversation_id']);
            $table->index(['ab_test_id', 'variant_id']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ab_test_results');
        Schema::dropIfExists('ab_test_variants');
        Schema::dropIfExists('ab_tests');
    }
}