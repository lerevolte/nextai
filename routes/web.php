<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WidgetController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Публичные роуты
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Виджет чата (публичный)
Route::prefix('widget')->group(function () {
    Route::get('/{bot:slug}', [WidgetController::class, 'show'])->name('widget.show');
    Route::post('/{bot:slug}/initialize', [WidgetController::class, 'initialize'])->name('widget.initialize');
    Route::post('/{bot:slug}/message', [WidgetController::class, 'sendMessage'])->name('widget.message');
    Route::post('/{bot:slug}/end', [WidgetController::class, 'endConversation'])->name('widget.end');
});

// API для виджета (альтернативный вариант)
Route::prefix('api/widget')->group(function () {
    Route::post('/{bot:slug}/message', [WidgetController::class, 'sendMessage']);
    Route::get('/{bot:slug}/history', [WidgetController::class, 'getHistory']);
    Route::post('/{bot:slug}/feedback', [WidgetController::class, 'submitFeedback']);
});

// Вебхуки для мессенджеров
Route::prefix('webhooks')->group(function () {
    Route::post('/telegram/{channel}', [WebhookController::class, 'telegram']);
    Route::post('/whatsapp/{channel}', [WebhookController::class, 'whatsapp']);
    Route::post('/vk/{channel}', [WebhookController::class, 'vk']);
});

// Аутентификация
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Защищенные роуты
Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    
    // Организация
    Route::prefix('organization')->group(function () {
        Route::get('/settings', [OrganizationController::class, 'settings'])->name('organization.settings');
        Route::put('/settings', [OrganizationController::class, 'update'])->name('organization.update');
        
        // Пользователи
        Route::resource('users', UserController::class)->middleware('permission:users.view');
    });
    
    // Боты
    Route::middleware(['organization.access'])->group(function () {
        Route::prefix('o/{organization:slug}')->group(function () {
            Route::resource('bots', BotController::class);
            
            // Каналы бота
            Route::prefix('bots/{bot}')->middleware('bot.access')->group(function () {
                Route::resource('channels', ChannelController::class);
                
                // База знаний
                Route::get('/knowledge', [KnowledgeBaseController::class, 'index'])->name('knowledge.index');
                Route::get('/knowledge/create', [KnowledgeBaseController::class, 'create'])->name('knowledge.create');
                Route::post('/knowledge', [KnowledgeBaseController::class, 'store'])->name('knowledge.store');
                Route::get('/knowledge/{item}/edit', [KnowledgeBaseController::class, 'edit'])->name('knowledge.edit');
                Route::put('/knowledge/{item}', [KnowledgeBaseController::class, 'update'])->name('knowledge.update');
                Route::delete('/knowledge/{item}', [KnowledgeBaseController::class, 'destroy'])->name('knowledge.destroy');
                
                // Диалоги
                Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
                Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
                Route::post('/conversations/{conversation}/takeover', [ConversationController::class, 'takeover'])->name('conversations.takeover');
                Route::post('/conversations/{conversation}/close', [ConversationController::class, 'close'])->name('conversations.close');
            });
            Route::prefix('bots/{bot}/knowledge')->middleware('bot.access')->group(function () {
                Route::get('/sources', [KnowledgeSourceController::class, 'index'])->name('knowledge.sources.index');
                Route::get('/sources/create', [KnowledgeSourceController::class, 'create'])->name('knowledge.sources.create');
                Route::post('/sources', [KnowledgeSourceController::class, 'store'])->name('knowledge.sources.store');
                Route::post('/sources/{source}/sync', [KnowledgeSourceController::class, 'sync'])->name('knowledge.sources.sync');
                Route::delete('/sources/{source}', [KnowledgeSourceController::class, 'destroy'])->name('knowledge.sources.destroy');
                Route::get('/import', [KnowledgeSourceController::class, 'import'])->name('knowledge.import');
                Route::post('/import', [KnowledgeSourceController::class, 'processImport'])->name('knowledge.import.process');
            });
        });
    });


});

// API роуты
Route::prefix('api')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // API для ботов
    Route::prefix('bots/{bot}')->middleware('bot.access')->group(function () {
        Route::get('/stats', [BotController::class, 'stats']);
        Route::post('/message', [BotController::class, 'processMessage']);
        Route::get('/conversations', [ConversationController::class, 'apiIndex']);
        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
    });
});