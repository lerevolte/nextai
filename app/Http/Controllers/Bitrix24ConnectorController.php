<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\CrmIntegration;
use App\Services\CRM\Providers\Bitrix24ConnectorProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Контроллер для обработки webhook от коннектора Битрикс24
 */
class Bitrix24ConnectorController extends Controller
{
    /**
     * Обработчик настройки коннектора (PLACEMENT_HANDLER)
     * Вызывается из интерфейса Битрикс24 при подключении коннектора
     */
    public function settings(Request $request)
    {
        try {
            // Получаем параметры из Битрикс24
            $placement = $request->input('PLACEMENT');
            $placementOptions = json_decode($request->input('PLACEMENT_OPTIONS', '{}'), true);
            
            if ($placement !== 'SETTING_CONNECTOR') {
                return response('Invalid placement', 400);
            }
            
            $lineId = intval($placementOptions['LINE'] ?? 0);
            $activeStatus = intval($placementOptions['ACTIVE_STATUS'] ?? 0);
            $connectorId = $placementOptions['CONNECTOR_ID'] ?? '';
            
            // Парсим connector_id чтобы получить bot_id
            // Формат: chatbot_{organization_id}_{bot_id}
            $parts = explode('_', $connectorId);
            if (count($parts) < 3) {
                return response('Invalid connector ID', 400);
            }
            
            $botId = $parts[2];
            $bot = Bot::find($botId);
            
            if (!$bot) {
                return response('Bot not found', 404);
            }
            
            // Находим CRM интеграцию для этого бота
            $integration = $bot->crmIntegrations()
                ->where('type', 'bitrix24')
                ->first();
            
            if (!$integration) {
                return response('CRM integration not found', 404);
            }
            
            // Активируем коннектор
            $provider = new Bitrix24ConnectorProvider($integration);
            $result = $provider->activateConnector($bot, $lineId, $activeStatus == 1);
            
            if ($result['success']) {
                return response('successfully');
            } else {
                return response('Error: ' . ($result['error'] ?? 'Unknown error'), 500);
            }
            
        } catch (\Exception $e) {
            Log::error('Bitrix24 connector settings error', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return response('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Обработчик события OnImConnectorMessageAdd
     * Вызывается когда оператор отправляет сообщение в открытой линии
     */
    public function handler(Request $request)
    {
        try {
            $event = $request->input('event');
            $data = $request->input('data', []);
            $auth = $request->input('auth', []);
            
            if ($event !== 'ONIMCONNECTORMESSAGEADD') {
                return response('OK');
            }
            
            $connectorId = $data['CONNECTOR'] ?? '';
            $messages = $data['MESSAGES'] ?? [];
            
            if (empty($connectorId) || empty($messages)) {
                return response('OK');
            }
            
            // Парсим connector_id чтобы получить bot_id
            $parts = explode('_', $connectorId);
            if (count($parts) < 3) {
                return response('OK');
            }
            
            $botId = $parts[2];
            $bot = Bot::find($botId);
            
            if (!$bot) {
                Log::warning('Bot not found for connector', ['connector_id' => $connectorId]);
                return response('OK');
            }
            
            // Находим CRM интеграцию
            $integration = $bot->crmIntegrations()
                ->where('type', 'bitrix24')
                ->first();
            
            if (!$integration) {
                Log::warning('CRM integration not found for bot', ['bot_id' => $botId]);
                return response('OK');
            }
            
            // Обрабатываем сообщения от оператора
            $provider = new Bitrix24ConnectorProvider($integration);
            $provider->handleOperatorMessage($data);
            
            return response('OK');
            
        } catch (\Exception $e) {
            Log::error('Bitrix24 connector handler error', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return response('OK'); // Всегда возвращаем OK чтобы не блокировать Битрикс24
        }
    }
    
    /**
     * Регистрация коннектора для бота
     * Вызывается из админки при создании интеграции
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'bot_id' => 'required|exists:bots,id',
                'integration_id' => 'required|exists:crm_integrations,id',
            ]);
            
            $bot = Bot::find($request->bot_id);
            $integration = CrmIntegration::find($request->integration_id);
            
            // Проверяем доступ
            if ($bot->organization_id !== $integration->organization_id) {
                return response()->json(['error' => 'Access denied'], 403);
            }
            
            if ($integration->type !== 'bitrix24') {
                return response()->json(['error' => 'Invalid integration type'], 400);
            }
            
            // Регистрируем коннектор
            $provider = new Bitrix24ConnectorProvider($integration);
            $result = $provider->registerConnector($bot);
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'connector_id' => $result['connector_id'],
                    'message' => 'Коннектор зарегистрирован. Теперь подключите его в Битрикс24: CRM > Контакт-центр > ' . $bot->name,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to register connector',
                ], 500);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to register Bitrix24 connector', [
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
     * Отмена регистрации коннектора
     */
    public function unregister(Request $request)
    {
        try {
            $request->validate([
                'bot_id' => 'required|exists:bots,id',
                'integration_id' => 'required|exists:crm_integrations,id',
            ]);
            
            $bot = Bot::find($request->bot_id);
            $integration = CrmIntegration::find($request->integration_id);
            
            // Проверяем доступ
            if ($bot->organization_id !== $integration->organization_id) {
                return response()->json(['error' => 'Access denied'], 403);
            }
            
            // Удаляем коннектор
            $provider = new Bitrix24ConnectorProvider($integration);
            $success = $provider->unregisterConnector($bot);
            
            return response()->json([
                'success' => $success,
                'message' => $success ? 'Коннектор удален' : 'Ошибка при удалении коннектора',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to unregister Bitrix24 connector', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}