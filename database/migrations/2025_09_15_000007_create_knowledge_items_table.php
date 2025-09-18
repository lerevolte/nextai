<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_base_id')->constrained()->onDelete('cascade');
            $table->string('type'); // manual, url, file, notion
            $table->string('title');
            $table->text('content');
            $table->text('source_url')->nullable();
            $table->json('metadata')->nullable();
            $table->json('embedding')->nullable(); // Векторное представление
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['knowledge_base_id', 'is_active']);
            $table->fullText(['title', 'content']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_items');
    }
};