// database/migrations/2024_01_01_000000_update_users_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained();
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar_url')->nullable()->after('phone');
            $table->json('settings')->nullable()->after('avatar_url');
            $table->timestamp('last_login_at')->nullable()->after('settings');
            $table->boolean('is_active')->default(true)->after('last_login_at');
            
            $table->index('organization_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn([
                'organization_id',
                'phone',
                'avatar_url', 
                'settings',
                'last_login_at',
                'is_active'
            ]);
        });
    }
};