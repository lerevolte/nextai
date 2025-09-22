<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Models\Bot;
use App\Models\CrmIntegration;
use App\Services\Bitrix24\Bitrix24AppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Контроллер для приложения Битрикс24 с авторизацией
 */
class Bitrix24AppController extends Controller
{
    protected Bitrix24AppService $appService;
    
    public function __construct(Bitrix24AppService $appService)
    {
        $this->appService = $appService;
    }
    
    /**
     * Установка приложения - показываем форму авторизации/регистрации
     */
    public function install(Request $request)
    {
        try {
            Log::info('Bitrix24 app install request', $request->all());
            
            // Сохраняем параметры установки в сессии
            session([
                'bitrix24_install' => [
                    'domain' => $request->input('DOMAIN'),
                    'auth_id' => $request->input('AUTH_ID'),
                    'auth_expires' => $request->input('AUTH_EXPIRES'),
                    'refresh_id' => $request->input('REFRESH_ID'),
                    'member_id' => $request->input('member_id'),
                    'app_sid' => $request->input('APP_SID'),
                    'user_info' => $request->input('USER_INFO', []),
                ]
            ]);
            
            // Проверяем, есть ли уже интеграция для этого домена
            $existingIntegration = CrmIntegration::where('type', 'bitrix24')
                ->where('settings->domain', $request->input('DOMAIN'))
                ->first();
            
            if ($existingIntegration) {
                // Интеграция уже существует
                return view('bitrix24.already-installed', [
                    'domain' => $request->input('DOMAIN'),
                    'organization' => $existingIntegration->organization,
                ]);
            }

            $installData = [
                'domain' => $request->input('DOMAIN'),
                'auth_id' => $request->input('AUTH_ID'),
                'auth_expires' => $request->input('AUTH_EXPIRES'),
                'refresh_id' => $request->input('REFRESH_ID'),
                'member_id' => $request->input('member_id'),
                'app_sid' => $request->input('APP_SID'),
                'user_info' => $request->input('USER_INFO', []),
            ];

            $encryptedInstallData = Crypt::encrypt($installData);
            
            // Показываем форму авторизации/регистрации
            return view('bitrix24.auth', [
                'domain' => $request->input('DOMAIN'),
                'user_info' => $request->input('USER_INFO', []),
                'install_data' => $encryptedInstallData
            ]);
            
        } catch (\Exception $e) {
            Log::error('Bitrix24 app install error', [
                'error' => $e->getMessage(),
            ]);
            
            return view('bitrix24.error', [
                'message' => 'Ошибка установки: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Авторизация существующего пользователя
     */
    public function login(Request $request)
    {
        info('app login');
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
                'install_data' => 'required|string',
            ]);
            
            // Пробуем авторизовать пользователя
            if (!Auth::attempt($request->only('email', 'password'))) {
                return back()->withErrors([
                    'email' => 'Неверный email или пароль',
                ]);
            }
            
            $user = Auth::user();
            
            // Проверяем, есть ли у пользователя организация
            if (!$user->organization_id) {
                return back()->withErrors([
                    'email' => 'У вашего аккаунта нет организации',
                ]);
            }
            info('app login createIntegrationForOrganization');
            try {
                $installData = Crypt::decrypt($request->install_data);
            } catch (DecryptException $e) {
                throw new \Exception('Не удалось расшифровать данные установки.');
            }
            // Создаем интеграцию для организации пользователя
            return $this->createIntegrationForOrganization($user->organization, $installData);
            
        } catch (\Exception $e) {
            Log::error('Bitrix24 login error', [
                'error' => $e->getMessage(),
            ]);
            
            return back()->withErrors([
                'error' => 'Ошибка авторизации: ' . $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Регистрация нового пользователя с организацией
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'organization_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'password' => 'required|min:8|confirmed',
                'name' => 'required|string|max:255',
                'install_data' => 'required|string'
            ]);
            
            // Создаем организацию
            $organization = Organization::create([
                'name' => $request->organization_name,
                'slug' => Str::slug($request->organization_name) . '-' . Str::random(6),
                'settings' => [
                    'created_from' => 'bitrix24_app',
                ],
            ]);
            
            // Создаем пользователя
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'organization_id' => $organization->id,
                'role' => 'admin', // Администратор организации
            ]);
            
            // Авторизуем пользователя
            Auth::login($user);

            try {
                $installData = Crypt::decrypt($request->install_data);
            } catch (DecryptException $e) {
                throw new \Exception('Не удалось расшифровать данные установки.');
            }
            
            // Создаем интеграцию
            return $this->createIntegrationForOrganization($organization, $installData);
            
        } catch (\Exception $e) {
            Log::error('Bitrix24 register error', [
                'error' => $e->getMessage(),
            ]);
            
            return back()->withErrors([
                'error' => 'Ошибка регистрации: ' . $e->getMessage(),
            ])->withInput();
        }
    }
    
    /**
     * Привязка к существующей организации по API ключу
     */
    public function linkByApiKey(Request $request)
    {
        try {
            $request->validate([
                'api_key' => 'required|string',
                'install_data' => 'required|string'
            ]);
            
            // Ищем организацию по API ключу
            $organization = Organization::where('api_key', $request->api_key)->first();
            
            if (!$organization) {
                return back()->withErrors([
                    'api_key' => 'Неверный API ключ организации',
                ]);
            }

            try {
                $installData = Crypt::decrypt($request->install_data);
            } catch (DecryptException $e) {
                throw new \Exception('Не удалось расшифровать данные установки.');
            }
            
            // Создаем интеграцию
            return $this->createIntegrationForOrganization($organization, $installData);
            
        } catch (\Exception $e) {
            Log::error('Bitrix24 link by API key error', [
                'error' => $e->getMessage(),
            ]);
            
            return back()->withErrors([
                'error' => 'Ошибка привязки: ' . $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Создание интеграции для организации
     */
    protected function createIntegrationForOrganization(Organization $organization, array $installData)
    {
        try {
            // $installData = session('bitrix24_install');
            
            // if (!$installData) {
            //     throw new \Exception('Данные установки не найдены');
            // }
            
            // Проверяем, нет ли уже интеграции
            $existingIntegration = $organization->crmIntegrations()
                ->where('type', 'bitrix24')
                //->where('settings->domain', $installData['domain'])
                ->first();
            
            if ($existingIntegration) {
                info('existingIntegration');
                // Обновляем существующую интеграцию
                // 1. Получаем текущие, уже существующие учетные данные (благодаря вашему акцессору они будут расшифрованы)
                $existingCredentials = $existingIntegration->credentials ?? [];

                // 2. Определяем новые данные от OAuth-приложения
                $newOAuthCredentials = [
                    'auth_id'      => $installData['auth_id'],
                    'auth_expires' => $installData['auth_expires'],
                    'refresh_id'   => $installData['refresh_id'],
                    'app_sid'      => $installData['app_sid'],
                    'domain'       => $installData['domain'],
                    'member_id'    => $installData['member_id'],
                ];

                // 3. Объединяем старые и новые данные. 
                // Если ключи совпадут, новые значения перезапишут старые. `webhook_url` сохранится.
                $mergedCredentials = array_merge($existingCredentials, $newOAuthCredentials);

                // 4. Обновляем интеграцию с объединенными данными
                $existingIntegration->update([
                    'credentials' => $mergedCredentials,
                    'is_active'   => true,
                    'settings'    => array_merge($existingIntegration->settings ?? [], [
                        'domain'          => $installData['domain'],
                        'app_installed'   => true,
                        'reinstalled_at'  => now()->toIso8601String(),
                        'installed_by_user' => Auth::id(),
                    ])
                ]);
                
                $integration = $existingIntegration;
            } else {
                info('no existingIntegration');
                // Создаем новую интеграцию
                $integration = $organization->crmIntegrations()->create([
                    'type' => 'bitrix24',
                    'name' => 'Битрикс24 - ' . $installData['domain'],
                    'credentials' => [
                        'auth_id' => $installData['auth_id'],
                        'auth_expires' => $installData['auth_expires'],
                        'refresh_id' => $installData['refresh_id'],
                        'app_sid' => $installData['app_sid'],
                        'domain' => $installData['domain'],
                        'member_id' => $installData['member_id'],
                    ],
                    'settings' => [
                        'domain' => $installData['domain'],
                        'app_installed' => true,
                        'installed_at' => now()->toIso8601String(),
                        'installed_by_user' => Auth::id(),
                    ],
                    'is_active' => true,
                ]);
            }
            
            // Регистрируем обработчики событий
            $this->appService->registerEventHandlers($integration);
            
            // Очищаем сессию
            //session()->forget('bitrix24_install');
            
            // Показываем страницу успешной установки
            return view('bitrix24.install-success', [
                'integration' => $integration,
                'organization' => $organization,
                'domain' => $installData['domain'],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to create Bitrix24 integration', [
                'organization_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Главная страница приложения (iframe)
     */
    public function index(Request $request)
    {
        try {
            $domain = $request->input('DOMAIN');
            $authId = $request->input('AUTH_ID');
            
            if (!$domain || !$authId) {
                return view('bitrix24.error', ['message' => 'Недостаточно параметров']);
            }
            
            // Находим интеграцию
            $integration = CrmIntegration::where('type', 'bitrix24')
                ->where('settings->domain', $domain)
                ->first();
            
            if (!$integration) {
                // Интеграция не найдена - предлагаем установить заново
                return view('bitrix24.not-installed', [
                    'domain' => $domain,
                    'message' => 'Интеграция не настроена. Пожалуйста, переустановите приложение.',
                ]);
            }
            
            // Обновляем токены если нужно
            $this->appService->updateAuthTokens($integration, $authId);
            
            // Получаем организацию и её боты
            $organization = $integration->organization;
            $bots = $organization->bots()->where('is_active', true)->get();
            
            // Получаем зарегистрированные коннекторы
            $connectors = $this->appService->getRegisteredConnectors($integration);
            
            return view('bitrix24.app', [
                'integration' => $integration,
                'organization' => $organization,
                'bots' => $bots,
                'connectors' => $connectors,
                'domain' => $domain,
                'authId' => $authId,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Bitrix24 app index error', [
                'error' => $e->getMessage(),
            ]);
            
            return view('bitrix24.error', ['message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    /**
     * Регистрация коннектора для бота
     */
    public function registerConnector(Request $request)
    {
        try {
            $request->validate([
                'bot_id' => 'required',
                'domain' => 'required',
                'auth_id' => 'required',
            ]);
            
            $botId = $request->input('bot_id');
            $domain = $request->input('domain');
            $authId = $request->input('auth_id');
            
            // Находим интеграцию
            $integration = CrmIntegration::where('type', 'bitrix24')
                ->where('settings->domain', $domain)
                ->first();
            
            if (!$integration) {
                return response()->json(['error' => 'Integration not found'], 404);
            }
            
            // Проверяем, что бот принадлежит той же организации
            $bot = Bot::find($botId);
            if (!$bot || $bot->organization_id !== $integration->organization_id) {
                return response()->json(['error' => 'Bot not found or access denied'], 403);
            }
            
            // Обновляем токены
            $this->appService->updateAuthTokens($integration, $authId);
            
            // Регистрируем коннектор
            $result = $this->appService->registerConnector($integration, $bot);
            
            if ($result['success']) {
                // Привязываем бота к интеграции если еще не привязан
                if (!$integration->bots()->where('bot_id', $bot->id)->exists()) {
                    $integration->bots()->attach($bot->id, [
                        'sync_contacts' => true,
                        'sync_conversations' => true,
                        'create_leads' => true,
                        'create_deals' => false,
                        'is_active' => true,
                        'connector_settings' => [
                            'connector_id' => $result['connector_id'],
                            'registered_at' => now()->toIso8601String(),
                        ]
                    ]);
                }
                
                return response()->json([
                    'success' => true,
                    'connector_id' => $result['connector_id'],
                    'message' => 'Коннектор успешно зарегистрирован',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to register connector',
                ], 500);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to register connector', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Удаление регистрации коннектора
     */
    public function unregisterConnector(Request $request)
    {
        try {
            $request->validate([
                'bot_id'  => 'required',
                'domain'  => 'required',
                'auth_id' => 'required',
            ]);

            $botId = $request->input('bot_id');
            $domain = $request->input('domain');
            $authId = $request->input('auth_id');

            // Находим интеграцию
            $integration = CrmIntegration::where('type', 'bitrix24')
                ->where('settings->domain', $domain)
                ->first();
            
            if (!$integration) {
                return response()->json(['success' => false, 'error' => 'Integration not found'], 404);
            }

            // Проверяем, что бот принадлежит той же организации
            $bot = Bot::find($botId);
            if (!$bot || $bot->organization_id !== $integration->organization_id) {
                return response()->json(['success' => false, 'error' => 'Bot not found or access denied'], 403);
            }

            // Обновляем токены (важно для API-запроса)
            $this->appService->updateAuthTokens($integration, $authId);

            // Вызываем сервис для удаления коннектора из Битрикс24
            // (Предполагается, что вы создадите этот метод в вашем Bitrix24AppService)
            $result = $this->appService->unregisterConnector($integration, $bot);
            
            if ($result['success']) {
                // Обновляем метаданные, убирая флаг регистрации
                $metadata = $bot->metadata ?? [];
                $metadata['bitrix24_connector_registered'] = false;
                unset($metadata['bitrix24_connector_id']);
                unset($metadata['bitrix24_connector_registered_at']);
                
                $bot->metadata = $metadata;
                $bot->save();
                
                // Очищаем кэш, чтобы при перезагрузке статус обновился
                \Illuminate\Support\Facades\Cache::flush();

                return response()->json(['success' => true, 'message' => 'Регистрация коннектора удалена']);
            }

            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to unregister connector'], 500);

        } catch (\Exception $e) {
            Log::error('Failed to unregister connector', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Активация коннектора (вызывается из Битрикс24)
     */
    public function activateConnector(Request $request)
    {
        info('activateConnector');
        try {
            $placement = $request->input('PLACEMENT');
            $placementOptions = json_decode($request->input('PLACEMENT_OPTIONS', '{}'), true);
            
            if ($placement !== 'SETTING_CONNECTOR') {
                return response('Invalid placement', 400);
            }
            
            $lineId = intval($placementOptions['LINE'] ?? 0);
            $activeStatus = intval($placementOptions['ACTIVE_STATUS'] ?? 0);
            $connectorId = $placementOptions['CONNECTOR'] ?? '';
            $domain = $request->input('DOMAIN');
            $authId = $request->input('AUTH_ID');
            
            // Находим интеграцию
            $integration = CrmIntegration::where('type', 'bitrix24')
                ->where('settings->domain', $domain)
                ->first();
            
            if (!$integration) {
                return response('Integration not found', 404);
            }
            
            // Парсим connector_id чтобы получить bot_id
            $parts = explode('_', $connectorId);
            if (count($parts) < 3) {
                return response('Invalid connector ID', 400);
            }
            
            $botId = $parts[2];
            $bot = Bot::find($botId);
            
            if (!$bot || $bot->organization_id !== $integration->organization_id) {
                return response('Bot not found', 404);
            }
            
            // Обновляем токены
            $this->appService->updateAuthTokens($integration, $authId);
            
            // Активируем коннектор
            $result = $this->appService->activateConnector($integration, $bot, $lineId, $activeStatus == 1);
            
            if ($result['success']) {
                return response('
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <script src="//api.bitrix24.com/api/v1/"></script>
                    </head>
                    <body>
                        <div style="padding: 20px; text-align: center;">
                            <h2 style="color: green;">✅ Успешно!</h2>
                            <p>Коннектор активирован</p>
                        </div>
                        <script>
                            BX24.init(function(){
                                BX24.closeApplication();
                            });
                        </script>
                    </body>
                    </html>
                ');
            } else {
                return response('Error: ' . ($result['error'] ?? 'Unknown error'), 500);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to activate connector', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            
            return response('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Обработчик событий от Битрикс24
     */
    public function eventHandler(Request $request)
    {
        try {
            $event = $request->input('event');
            $data = $request->input('data');
            $auth = $request->input('auth');
            
            Log::info('Bitrix24 event received', [
                'event' => $event,
                'domain' => $auth['domain'] ?? null,
            ]);
            
            // Находим интеграцию по домену
            $integration = CrmIntegration::where('type', 'bitrix24')
                ->where('settings->domain', $auth['domain'] ?? '')
                ->first();
            
            if (!$integration) {
                Log::warning('Integration not found for domain', [
                    'domain' => $auth['domain'] ?? null,
                ]);
                return response('OK');
            }
            
            // Обрабатываем событие
            switch ($event) {
                case 'ONIMCONNECTORMESSAGEADD':
                    $this->appService->handleConnectorMessage($integration, $data);
                    break;
                    
                case 'ONAPPUNINSTALL':
                    $this->handleAppUninstall($integration);
                    break;
                    
                default:
                    Log::info('Unhandled event', ['event' => $event]);
            }
            
            return response('OK');
            
        } catch (\Exception $e) {
            Log::error('Event handler error', [
                'error' => $e->getMessage(),
                'event' => $request->input('event'),
            ]);
            
            return response('OK');
        }
    }
    
    /**
     * Обработка удаления приложения
     */
    protected function handleAppUninstall($integration)
    {
        try {
            // Деактивируем интеграцию
            $integration->update([
                'is_active' => false,
                'settings' => array_merge($integration->settings ?? [], [
                    'app_installed' => false,
                    'uninstalled_at' => now()->toIso8601String(),
                ])
            ]);
            
            // Удаляем регистрации коннекторов
            foreach ($integration->bots as $bot) {
                $this->appService->unregisterConnector($integration, $bot);
            }
            
            Log::info('Bitrix24 app uninstalled', [
                'integration_id' => $integration->id,
                'domain' => $integration->settings['domain'] ?? null,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to handle app uninstall', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
}