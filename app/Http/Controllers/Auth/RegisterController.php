<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'organization_name' => 'required|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            // Создаем организацию
            $organization = Organization::create([
                'name' => $request->organization_name,
                'slug' => Str::slug($request->organization_name) . '-' . Str::random(6),
                'settings' => [
                    'plan' => 'free',
                    'trial_ends_at' => now()->addDays(14),
                ],
            ]);

            // Создаем пользователя
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'organization_id' => $organization->id,
            ]);

            // Назначаем роль владельца
            $user->assignRole('owner');

            // Создаем демо-бота
            $this->createDemoBot($organization);

            DB::commit();

            // Отправляем письмо подтверждения
            $user->sendEmailVerificationNotification();

            auth()->login($user);

            return redirect()->route('dashboard')
                ->with('success', 'Добро пожаловать! Ваша организация создана.');

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Ошибка при создании аккаунта. Попробуйте еще раз.']);
        }
    }

    protected function createDemoBot(Organization $organization)
    {
        $bot = $organization->bots()->create([
            'name' => 'Демо помощник',
            'slug' => 'demo-bot-' . Str::random(6),
            'description' => 'Это ваш первый бот. Настройте его под ваши нужды!',
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
            'system_prompt' => 'Ты - дружелюбный помощник. Отвечай кратко и по существу.',
            'welcome_message' => 'Здравствуйте! Я виртуальный помощник. Чем могу помочь?',
            'temperature' => 0.7,
            'max_tokens' => 500,
        ]);

        // Создаем веб-канал
        $bot->channels()->create([
            'type' => 'web',
            'name' => 'Виджет для сайта',
            'settings' => [
                'position' => 'bottom-right',
                'color' => '#4F46E5',
                'show_avatar' => true,
            ],
        ]);
    }
}