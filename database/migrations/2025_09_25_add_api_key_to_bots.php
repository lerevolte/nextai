<?php
// database/migrations/2025_09_25_add_api_key_to_bots.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            if (!Schema::hasColumn('bots', 'api_key')) {
                $table->string('api_key', 50)->unique()->nullable()->after('slug');
                $table->index('api_key');
            }
        });
        
        // Генерируем API ключи для существующих ботов
        $bots = \App\Models\Bot::whereNull('api_key')->get();
        foreach ($bots as $bot) {
            $bot->update([
                'api_key' => 'bot_' . Str::random(32)
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            if (Schema::hasColumn('bots', 'api_key')) {
                $table->dropIndex(['api_key']);
                $table->dropColumn('api_key');
            }
        });
    }
};