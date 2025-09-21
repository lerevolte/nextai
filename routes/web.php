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
use App\Http\Controllers\KnowledgeSourceController;
use App\Http\Controllers\AbTestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\PerformanceController;
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
    Route::post('/telegram/{channel}', [WebhookController::class, 'telegram'])->name('webhooks.telegram');
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
                Route::get('/knowledge/{item}/versions', [KnowledgeBaseController::class, 'versions'])->name('knowledge.versions');
                Route::post('/knowledge/{item}/restore-version', [KnowledgeBaseController::class, 'restoreVersion'])->name('knowledge.versions.restore');
                Route::post('/knowledge/{item}/versions/compare', [KnowledgeBaseController::class, 'compareVersions'])->name('knowledge.versions.compare');
                Route::delete('/knowledge/{item}/versions/{version}/delete', [KnowledgeBaseController::class, 'deleteVersion'])->name('knowledge.versions.delete');
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
                Route::get('/knowledge/sources/{source}/logs', [KnowledgeSourceController::class, 'logs'])->name('knowledge.sources.logs');


                Route::get('/import', [KnowledgeSourceController::class, 'import'])->name('knowledge.import');
                Route::post('/import', [KnowledgeSourceController::class, 'processImport'])->name('knowledge.import.process');
            });

            Route::prefix('crm')->group(function () {
                Route::get('/', [App\Http\Controllers\CrmIntegrationController::class, 'index'])->name('crm.index');
                Route::get('/create', [App\Http\Controllers\CrmIntegrationController::class, 'create'])->name('crm.create');
                Route::post('/', [App\Http\Controllers\CrmIntegrationController::class, 'store'])->name('crm.store');
                Route::get('/{integration}', [App\Http\Controllers\CrmIntegrationController::class, 'show'])->name('crm.show');
                Route::get('/{integration}/edit', [App\Http\Controllers\CrmIntegrationController::class, 'edit'])->name('crm.edit');
                Route::put('/{integration}', [App\Http\Controllers\CrmIntegrationController::class, 'update'])->name('crm.update');
                Route::delete('/{integration}', [App\Http\Controllers\CrmIntegrationController::class, 'destroy'])->name('crm.destroy');
                Route::post('/{integration}/test', [App\Http\Controllers\CrmIntegrationController::class, 'test'])->name('crm.test');
                
                // API методы
                Route::post('/{integration}/test-connection', [App\Http\Controllers\CrmIntegrationController::class, 'testConnection'])->name('crm.test-connection');
                Route::post('/{integration}/sync-conversation', [App\Http\Controllers\CrmIntegrationController::class, 'syncConversation'])->name('crm.sync-conversation');
                Route::post('/{integration}/bulk-sync', [App\Http\Controllers\CrmIntegrationController::class, 'bulkSync'])->name('crm.bulk-sync');
                Route::get('/{integration}/fields', [App\Http\Controllers\CrmIntegrationController::class, 'getFields'])->name('crm.fields');
                Route::get('/{integration}/users', [App\Http\Controllers\CrmIntegrationController::class, 'getUsers'])->name('crm.users');
                Route::get('/{integration}/pipelines', [App\Http\Controllers\CrmIntegrationController::class, 'getPipelines'])->name('crm.pipelines');
                Route::post('/{integration}/export', [App\Http\Controllers\CrmIntegrationController::class, 'export'])->name('crm.export');
                
                // Настройки бота
                Route::get('/{integration}/bot/{bot}', [App\Http\Controllers\CrmIntegrationController::class, 'botSettings'])->name('crm.bot-settings');
                Route::put('/{integration}/bot/{bot}', [App\Http\Controllers\CrmIntegrationController::class, 'updateBotSettings'])->name('crm.bot-settings.update');

                // Salebot специфичные методы
                Route::group([
                    'prefix' => '/{integration}/salebot',
                    'middleware' => [function (Request $request, $next) {
                        $integration = $request->route('integration');
                        // Проверяем, что модель была найдена и ее тип соответствует 'salebot'
                        if (!$integration || $integration->type !== 'salebot') {
                            abort(404);
                        }
                        return $next($request);
                    }]
                ], function () {
                    Route::get('/funnels', [App\Http\Controllers\SalebotController::class, 'getFunnels'])->name('crm.salebot.funnels');
                    Route::get('/funnel-blocks', [App\Http\Controllers\SalebotController::class, 'getFunnelBlocks'])->name('crm.salebot.funnel-blocks');
                    Route::post('/start-funnel', [App\Http\Controllers\SalebotController::class, 'startFunnel'])->name('crm.salebot.start-funnel');
                    Route::post('/stop-funnel', [App\Http\Controllers\SalebotController::class, 'stopFunnel'])->name('crm.salebot.stop-funnel');
                    Route::post('/transfer-operator', [App\Http\Controllers\SalebotController::class, 'transferToOperator'])->name('crm.salebot.transfer-operator');
                    Route::post('/broadcast', [App\Http\Controllers\SalebotController::class, 'broadcast'])->name('crm.salebot.broadcast');
                    Route::get('/funnel-stats', [App\Http\Controllers\SalebotController::class, 'getFunnelStats'])->name('crm.salebot.funnel-stats');
                    Route::post('/create-variable', [App\Http\Controllers\SalebotController::class, 'createVariable'])->name('crm.salebot.create-variable');
                    Route::get('/bots', [App\Http\Controllers\SalebotController::class, 'getBots'])->name('crm.salebot.bots');
                    Route::post('/sync-variables', [App\Http\Controllers\SalebotController::class, 'syncClientVariables'])->name('crm.salebot.sync-variables');
                });
            });

            // Улучшенный дашборд
            Route::get('/analytics', [DashboardController::class, 'index'])->name('analytics.index');
            Route::get('/analytics/refresh', [DashboardController::class, 'refreshMetrics'])->name('analytics.refresh');
            Route::post('/analytics/export', [DashboardController::class, 'exportMetrics'])->name('analytics.export');
            
            // A/B тестирование
            Route::prefix('ab-tests')->group(function () {
                Route::get('/', [AbTestController::class, 'index'])->name('ab-tests.index');
                Route::get('/create', [AbTestController::class, 'create'])->name('ab-tests.create');
                Route::post('/', [AbTestController::class, 'store'])->name('ab-tests.store');
                Route::get('/{test}', [AbTestController::class, 'show'])->name('ab-tests.show');
                Route::get('/{test}/analysis', [AbTestController::class, 'analysis'])->name('ab-tests.analysis');
                Route::post('/{test}/complete', [AbTestController::class, 'complete'])->name('ab-tests.complete');
                Route::post('/{test}/pause', [AbTestController::class, 'pause'])->name('ab-tests.pause');
                Route::delete('/{test}', [AbTestController::class, 'destroy'])->name('ab-tests.destroy');
            });
            
            // Отчеты
            Route::prefix('reports')->group(function () {
                Route::get('/', [ReportController::class, 'index'])->name('reports.index');
                Route::post('/generate', [ReportController::class, 'generate'])->name('reports.generate');
                Route::get('/scheduled', [ReportController::class, 'scheduled'])->name('reports.scheduled');
                Route::post('/schedule', [ReportController::class, 'schedule'])->name('reports.schedule');
                Route::get('/download/{report}', [ReportController::class, 'download'])->name('reports.download');
                Route::delete('/scheduled/{report}', [ReportController::class, 'deleteScheduled'])->name('reports.scheduled.delete');
            });
            
            // Мониторинг производительности
            Route::prefix('performance')->group(function () {
                Route::get('/', [PerformanceController::class, 'index'])->name('performance.index');
                Route::get('/metrics', [PerformanceController::class, 'metrics'])->name('performance.metrics');
                Route::post('/optimize', [PerformanceController::class, 'optimize'])->name('performance.optimize');
                Route::get('/recommendations', [PerformanceController::class, 'recommendations'])->name('performance.recommendations');
            });

        });
    });


});
Route::prefix('webhooks/crm')->group(function () {
    Route::post('/{type}', [App\Http\Controllers\CrmIntegrationController::class, 'webhook'])->name('webhooks.crm');
});

Route::prefix('webhooks/crm/bitrix24/connector')->group(function () {
    // Обработчик настройки коннектора (вызывается из Битрикс24 при подключении)
    Route::post('/settings', [App\Http\Controllers\Bitrix24ConnectorController::class, 'settings'])
        ->name('webhooks.bitrix24.connector.settings')
        ->withoutMiddleware(['web', 'csrf']);
    
    // Обработчик событий от коннектора (сообщения от операторов)
    Route::post('/handler', [App\Http\Controllers\Bitrix24ConnectorController::class, 'handler'])
        ->name('webhooks.bitrix24.connector.handler')
        ->withoutMiddleware(['web', 'csrf']);
});

// API для управления коннектором (защищенные)
Route::middleware(['auth'])->prefix('api/crm/bitrix24/connector')->group(function () {
    // Регистрация коннектора для бота
    Route::post('/register', [App\Http\Controllers\Bitrix24ConnectorController::class, 'register'])
        ->name('api.bitrix24.connector.register');
    
    // Отмена регистрации коннектора
    Route::post('/unregister', [App\Http\Controllers\Bitrix24ConnectorController::class, 'unregister'])
        ->name('api.bitrix24.connector.unregister');
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