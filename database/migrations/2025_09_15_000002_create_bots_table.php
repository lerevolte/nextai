<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('avatar_url')->nullable();
            
            // AI настройки
            $table->string('ai_provider')->default('openai'); // openai, gemini, deepseek
            $table->string('ai_model')->default('gpt-4o-mini');
            $table->text('system_prompt');
            $table->text('welcome_message')->nullable();
            $table->float('temperature', 2, 1)->default(0.7);
            $table->integer('max_tokens')->default(500);
            
            // Настройки поведения
            $table->boolean('is_active')->default(true);
            $table->boolean('knowledge_base_enabled')->default(false);
            $table->boolean('collect_contacts')->default(true);
            $table->boolean('human_handoff_enabled')->default(false);
            $table->json('working_hours')->nullable();
            $table->json('settings')->nullable();
            
            $table->timestamps();
            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};