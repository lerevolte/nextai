<?php

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
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'api_key')) {
                $table->string('api_key', 50)->unique()->nullable()->after('slug');
                $table->index('api_key');
            }
        });
        
        // Генерируем API ключи для существующих организаций
        $organizations = \App\Models\Organization::whereNull('api_key')->get();
        foreach ($organizations as $organization) {
            $organization->update([
                'api_key' => 'org_' . Str::random(32)
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'api_key')) {
                $table->dropIndex(['api_key']);
                $table->dropColumn('api_key');
            }
        });
    }
};