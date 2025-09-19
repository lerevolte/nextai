<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\CrmIntegration;
use App\Models\Organization;
use App\Services\CRM\CrmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmIntegrationController extends Controller
{
    protected CrmService $crmService;

    public function __construct(CrmService $crmService)
    {
        $this->crmService = $crmService;
    }

    /**
     * Список CRM интеграций организации
     */
    public function index(Organization $organization)
    {
        $integrations = $organization->crmIntegrations()
            ->with(['bots' => function($query) {
                $query->select('bots.id', 'bots.name');
            }])
            ->withCount('syncEntities')
            ->get();

        $availableTypes = $this->crmService->getAvailableTypes();

        return view('crm.index', compact('organization', 'integrations', 'availableTypes'));
    }

    /**
     * Форма создания интеграции
     */
    public function create(Organization $organization)
    {
        $availableTypes = $this->crmService->getAvailableTypes();
        $bots = $organization->bots()->where('is_active', true)->get();

        return view('crm.create', compact('organization', 'availableTypes', 'bots'));
    }

    /**
     * Сохранение новой интеграции
     */
    public function store(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'type' => 'required|in:bitrix24,amocrm,avito,salebot',
            'name' => 'required|string|max:255',
            'credentials' => 'required|array',
            'settings' => 'nullable|array',
            'bot_ids' => 'nullable|array',
            'bot_ids.*' => 'exists:bots,id',
        ]); $request->validate([
            'type' => 'required|in:bitrix24,amocrm,avito',
            'name' => 'required|string|max:255',
            'credentials' => 'required|array',
            'settings' => 'nullable|array',
            'bot_ids' => 'nullable|array',
            'bot_ids.*' => 'exists:bots,id',
        ]);

        // Проверяем, нет ли уже интеграции этого типа
        if ($organization->crmIntegrations()->where('type', $validated['type'])->exists()) {
            return back()->withErrors(['type' => 'Интеграция этого типа уже существует']);
        }

        DB::beginTransaction();
        
        try {
            // Создаем интеграцию
            $integration = $organization->crmIntegrations()->create([
                'type' => $validated['type'],
                'name' => $validated['name'],
                'credentials' => $validated['credentials'],
                'settings' => $validated['settings'] ?? [],
                'is_active' => false, // Сначала неактивна
            ]);

            // Тестируем подключение
            if ($this->crmService->testConnection($integration)) {
                $integration->update(['is_active' => true]);
                
                // Автоматическая настройка
                $this->crmService->autoSetup($integration);
            } else {
                DB::rollback();
                return back()
                    ->withInput()
                    ->withErrors(['connection' => 'Не удалось подключиться к CRM. Проверьте данные доступа.']);
            }

            // Привязываем к ботам
            if (!empty($validated['bot_ids'])) {
                foreach ($validated['bot_ids'] as $botId) {
                    $bot = Bot::find($botId);
                    if ($bot && $bot->organization_id === $organization->id) {
                        $integration->bots()->attach($botId, [
                            'sync_contacts' => true,
                            'sync_conversations' => true,
                            'create_leads' => true,
                            'create_deals' => false,
                            'is_active' => true,
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()
                ->route('crm.show', [$organization, $integration])
                ->with('success', 'CRM интеграция успешно добавлена');

        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('Failed to create CRM integration', [
                'organization_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Ошибка при создании интеграции: ' . $e->getMessage()]);
        }
    }

    /**
     * Просмотр интеграции
     */
    public function show(Organization $organization, CrmIntegration $integration)
    {
        if ($integration->organization_id !== $organization->id) {
            abort(403);
        }

        $integration->load([
            'bots',
            'syncEntities' => function($query) {
                $query->latest()->limit(20);
            },
            'syncLogs' => function($query) {
                $query->latest()->limit(20);
            },
        ]);

        // Получаем статистику
        $stats = $this->crmService->getSyncStats($integration, now()->subMonth(), now());

        // Получаем настройки типа
        $typeSettings = $this->crmService->getIntegrationSettings($integration->type);

        return view('crm.show', compact('organization', 'integration', 'stats', 'typeSettings'));
    }

    /**
     * Форма редактирования интеграции
     */
    public function edit(Organization $organization, CrmIntegration $integration)
    {
        if ($integration->organization_id !== $organization->id) {
            abort(403);
        }

        $bots = $organization->bots()->where('is_active', true)->get();
        $connectedBotIds = $integration->bots()->pluck('bots.id')->toArray();
        $typeSettings = $this->crmService->getIntegrationSettings($integration->type);

        return view('crm.edit', compact('organization', 'integration', 'bots', 'connectedBotIds', 'typeSettings'));
    }

    /**
     * Обновление интеграции
     */
    public function update(Request $request, Organization $organization, CrmIntegration $integration)
    {
        if ($integration->organization_id !== $organization->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        // Обновляем credentials только если переданы
        if ($request->has('credentials')) {
            $validated['credentials'] = $request->credentials;
        }

        $integration->update($validated);

        // Обновляем привязку к ботам
        if ($request->has('bot_ids')) {
            $botIds = $request->bot_ids;
            $syncData = [];
            
            foreach ($botIds as $botId) {
                $bot = Bot::find($botId);
                if ($bot && $bot->organization_id === $organization->id) {
                    $syncData[$botId] = [
                        'sync_contacts' => $request->boolean("bot_settings.{$botId}.sync_contacts", true),
                        'sync_conversations' => $request->boolean("bot_settings.{$botId}.sync_conversations", true),
                        'create_leads' => $request->boolean("bot_settings.{$botId}.create_leads", true),
                        'create_deals' => $request->boolean("bot_settings.{$botId}.create_deals", false),
                        'lead_source' => $request->input("bot_settings.{$botId}.lead_source"),
                        'responsible_user_id' => $request->input("bot_settings.{$botId}.responsible_user_id"),
                        'pipeline_settings' => $request->input("bot_settings.{$botId}.pipeline_settings", []),
                        'is_active' => $request->boolean("bot_settings.{$botId}.is_active", true),
                    ];
                }
            }
            
            $integration->bots()->sync($syncData);
        }

        return redirect()
            ->route('crm.show', [$organization, $integration])
            ->with('success', 'Интеграция обновлена');
    }

    /**
     * Удаление интеграции
     */
    public function destroy(Organization $organization, CrmIntegration $integration)
    {
        if ($integration->organization_id !== $organization->id) {
            abort(403);
        }

        $integration->delete();

        return redirect()
            ->route('crm.index', $organization)
            ->with('success', 'CRM интеграция удалена');
    }

    /**
     * Тестирование подключения
     */
    public function testConnection(Organization $organization, CrmIntegration $integration)
    {
        if ($integration->organization_id !== $organization->id) {
            abort(403);
        }

        $success = $this->crmService->testConnection($integration);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Подключение успешно' : 'Не удалось подключиться',
        ]);
    }

    /**
     * Синхронизация диалога
     */
    public function syncConversation(Request $request, Organization $organization, CrmIntegration $integration)
    {
        if ($integration->organization_id !== $organization->id) {
            abort(403);
        }

        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
        ]);

        $conversation = \App\Models\Conversation::find($request->conversation_id);
        
        // Проверяем доступ
        if ($conversation->bot->organization_id !== $organization->id) {
            abort(403);
        }

        try {
            $provider = $this->crmService->getProvider($integration);
            $result = $provider->syncConversation($conversation);
            
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Массовая синхронизация
     */
    public function bulkSync(Request $request, Organization $organization, CrmIntegration $integration)
    {
        if ($integration->organization_id !== $organization->id) {
            abort(403);
        }

        $request->validate([
            'conversation_ids' => 'required|array',
            'conversation_ids.*' => 'exists:conversations,id',
        ]);

        $results = $this->crmService->bulkSyncConversations($request->conversation_ids);

        return response()->json($results);
    }

    /**
     * Получение полей CRM
     */
    public function getFields(Organization $organization, CrmIntegration $integration)
    {
        if ($integration->organization_id !== $organization->id) {
            abort(403);
        }

        try {
            $provider = $this->crmService->getProvider($integration);
            
            $fields = [
                'leads' => $provider->getFields('lead'),
                'contacts' => $provider->getFields('contact'),
                'companies' => $provider->getFields('company'),
            ];
            
            return response()->json([
                'success' => true,
                'fields' => $fields,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение пользователей CRM
     */
    public function getUsers(Organization $organization, CrmIntegration $integration)
    {
        if ($integration->organization_id !== $organization->id) {
            abort(403);
        }

        try {
            $provider = $this->crmService->getProvider($integration);
            $users = $provider->getUsers();
            
            return response()->json([
                'success' => true,
                'users' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение воронок CRM
     */
    public function getPipelines(Organization $organization, CrmIntegration $integration)
    {
        if ($integration->organization_id !== $organization->id) {
            abort(403);
        }

        try {
            $provider = $this->crmService->getProvider($integration);
            $pipelines = $provider->getPipelines();
            
            return response()->json([
                'success' => true,
                'pipelines' => $pipelines,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Экспорт диалогов
     */
    public function export(Request $request, Organization $organization, CrmIntegration $integration)
    {
        if ($integration->organization_id !== $organization->id) {
            abort(403);
        }

        $filters = $request->validate([
            'bot_id' => 'nullable|exists:bots,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'status' => 'nullable|in:active,closed,waiting_operator',
            'limit' => 'nullable|integer|min:1|max:1000',
            'skip_synced' => 'nullable|boolean',
        ]);

        $results = $this->crmService->exportConversations($integration, $filters);

        return response()->json($results);
    }

    /**
     * Webhook endpoint
     */
    public function webhook(Request $request, string $type)
    {
        $signature = $request->header('X-Signature');
        $data = $request->all();

        $success = $this->crmService->handleWebhook($type, $data, $signature);

        return response()->json(['success' => $success]);
    }

    /**
     * Настройки бота для интеграции
     */
    public function botSettings(Organization $organization, CrmIntegration $integration, Bot $bot)
    {
        if ($integration->organization_id !== $organization->id || $bot->organization_id !== $organization->id) {
            abort(403);
        }

        $settings = $integration->bots()
            ->wherePivot('bot_id', $bot->id)
            ->first();

        if (!$settings) {
            abort(404);
        }

        // Получаем доступные настройки для типа CRM
        $provider = $this->crmService->getProvider($integration);
        $users = $provider->getUsers();
        $pipelines = $provider->getPipelines();

        return view('crm.bot-settings', compact('organization', 'integration', 'bot', 'settings', 'users', 'pipelines'));
    }

    /**
     * Обновление настроек бота
     */
    public function updateBotSettings(Request $request, Organization $organization, CrmIntegration $integration, Bot $bot)
    {
        if ($integration->organization_id !== $organization->id || $bot->organization_id !== $organization->id) {
            abort(403);
        }

        $validated = $request->validate([
            'sync_contacts' => 'boolean',
            'sync_conversations' => 'boolean',
            'create_leads' => 'boolean',
            'create_deals' => 'boolean',
            'lead_source' => 'nullable|string',
            'responsible_user_id' => 'nullable|string',
            'pipeline_settings' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $integration->bots()->updateExistingPivot($bot->id, $validated);

        return redirect()
            ->route('crm.show', [$organization, $integration])
            ->with('success', 'Настройки бота обновлены');
    }
}