<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Сброс кэша ролей и прав
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Создаем права
        $permissions = [
            // Организация
            'organization.view',
            'organization.update',
            'organization.delete',
            
            // Пользователи
            'users.view',
            'users.create',
            'users.update', 
            'users.delete',
            
            // Боты
            'bots.view',
            'bots.create',
            'bots.update',
            'bots.delete',
            'bots.settings',
            
            // Каналы
            'channels.view',
            'channels.create',
            'channels.update',
            'channels.delete',
            
            // База знаний
            'knowledge.view',
            'knowledge.create',
            'knowledge.update',
            'knowledge.delete',
            
            // Диалоги
            'conversations.view',
            'conversations.export',
            'conversations.delete',
            'conversations.takeover', // Перехват диалога оператором
            
            // Аналитика
            'analytics.view',
            'analytics.export',
            
            // Биллинг
            'billing.view',
            'billing.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Создаем роли и назначаем права
        
        // Владелец организации - полный доступ
        $owner = Role::create(['name' => 'owner']);
        $owner->givePermissionTo(Permission::all());

        // Администратор - все кроме удаления организации и биллинга
        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo([
            'organization.view',
            'organization.update',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'bots.view',
            'bots.create',
            'bots.update',
            'bots.delete',
            'bots.settings',
            'channels.view',
            'channels.create',
            'channels.update',
            'channels.delete',
            'knowledge.view',
            'knowledge.create',
            'knowledge.update',
            'knowledge.delete',
            'conversations.view',
            'conversations.export',
            'conversations.delete',
            'conversations.takeover',
            'analytics.view',
            'analytics.export',
        ]);

        // Менеджер - управление ботами и контентом
        $manager = Role::create(['name' => 'manager']);
        $manager->givePermissionTo([
            'bots.view',
            'bots.update',
            'bots.settings',
            'channels.view',
            'channels.update',
            'knowledge.view',
            'knowledge.create',
            'knowledge.update',
            'conversations.view',
            'conversations.takeover',
            'analytics.view',
        ]);

        // Оператор - только работа с диалогами
        $operator = Role::create(['name' => 'operator']);
        $operator->givePermissionTo([
            'bots.view',
            'conversations.view',
            'conversations.takeover',
        ]);

        // Аналитик - только просмотр статистики
        $analyst = Role::create(['name' => 'analyst']);
        $analyst->givePermissionTo([
            'bots.view',
            'conversations.view',
            'analytics.view',
            'analytics.export',
        ]);
    }
}