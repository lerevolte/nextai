<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Models\Bot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InitialSetupSeeder extends Seeder
{
    public function run()
    {
        // Создаем организацию
        $organization = Organization::create([
            'name' => 'Моя компания',
            'slug' => 'my-company-' . Str::random(6),
            'settings' => [
                'plan' => 'pro',
                'trial_ends_at' => now()->addDays(30),
            ],
            'bots_limit' => 10,
            'messages_limit' => 100000,
            'is_active' => true,
        ]);

        // Создаем администратора
        $admin = User::create([
            'name' => 'Администратор',
            'email' => 'admin@example.com', // Измените на ваш email
            'password' => Hash::make('password'), // Измените пароль!
            'organization_id' => $organization->id,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Назначаем роль владельца
        $admin->assignRole('owner');

        // Создаем демо-бота
        $bot = Bot::create([
            'organization_id' => $organization->id,
            'name' => 'Тестовый помощник',
            'slug' => 'test-bot-' . Str::random(6),
            'description' => 'Демо бот для тестирования системы',
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
            'system_prompt' => 'Ты дружелюбный помощник. Отвечай кратко и по существу на русском языке.',
            'welcome_message' => 'Здравствуйте! Я ваш виртуальный помощник. Чем могу помочь?',
            'temperature' => 0.7,
            'max_tokens' => 500,
            'is_active' => true,
        ]);

        // Создаем веб-канал для бота
        $bot->channels()->create([
            'type' => 'web',
            'name' => 'Виджет для сайта',
            'is_active' => true,
            'settings' => [
                'position' => 'bottom-right',
                'color' => '#4F46E5',
                'show_avatar' => true,
            ],
        ]);

        $this->command->info('✅ Создан пользователь: admin@example.com / password');
        $this->command->info('✅ Создана организация: ' . $organization->name);
        $this->command->info('✅ Создан демо-бот: ' . $bot->name);
    }
}