<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_base_id')->constrained()->onDelete('cascade');
            $table->foreignId('knowledge_item_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('knowledge_source_id')->nullable()->constrained()->onDelete('set null');
            $table->string('title');
            $table->text('content');
            $table->string('source_url')->nullable();
            $table->integer('chunk_index')->default(0);
            $table->integer('total_chunks')->default(1);
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['knowledge_base_id', 'is_active']);
            $table->index('knowledge_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};