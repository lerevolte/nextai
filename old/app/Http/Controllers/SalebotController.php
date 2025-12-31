<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Conversation;
use App\Models\CrmIntegration;
use App\Models\Organization;
use App\Services\CRM\Providers\SalebotProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SalebotController extends Controller
{
    /**
     * Получить список воронок для интеграции
     */
    public function getFunnels(Organization $organization, CrmIntegration $integration)
    {
        if ($integration->organization_id !== $organization->id || $integration->type !== 'salebot') {
            abort(403);
        }

        try {
            $provider = new SalebotProvider($integration);
            $funnels = $provider->getPipelines(); // В Salebot воронки = pipelines
            
            return response()->json([
                'success' => true,
                'funnels' => $funnels,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get Salebot funnels', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить блоки воронки
     */
    public function getFunnelBlocks(Organization $organization, CrmIntegration $integration, Request $request)
    {
        if ($integration->organization_id !== $organization->id || $integration->type !== 'salebot') {
            abort(403);
        }

        $request->validate([
            'funnel_id' => 'required|string',
        ]);

        try {
            $provider = new SalebotProvider($integration);
            $blocks = $provider->getPipelineStages($request->funnel_id);
            
            return response()->json([
                'success' => true,
                'blocks' => $blocks,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get Salebot funnel blocks', [
                'integration_id' => $integration->id,
                'funnel_id' => $request->funnel_id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Запустить воронку для диалога
     */
    public function startFunnel(Organization $organization, CrmIntegration $integration, Request $request)
    {
        if ($integration->organization_id !== $organization->id || $integration->type !== 'salebot') {
            abort(403);
        }

        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'funnel_id' => 'required|string',
            'block_id' => 'nullable|string',
        ]);

        $conversation = Conversation::find($request->conversation_id);
        
        // Проверяем доступ
        if ($conversation->bot->organization_id !== $organization->id) {
            abort(403);
        }

        try {
            $provider = new SalebotProvider($integration);
            
            // Получаем или создаем клиента
            $clientId = $conversation->metadata['salebot_client_id'] ?? null;
            
            if (!$clientId) {
                $result = $provider->createLead($conversation, [
                    'funnel_id' => $request->funnel_id,
                    'block_id' => $request->block_id,
                ]);
                $clientId = $result['client_id'] ?? null;
            } else {
                // Запускаем воронку для существующего клиента
                $provider->startFunnel($clientId, $request->funnel_id, $request->block_id);
            }
            
            return response()->json([
                'success' => true,
                'client_id' => $clientId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to start Salebot funnel', [
                'conversation_id' => $request->conversation_id,
                'funnel_id' => $request->funnel_id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Остановить воронку для диалога
     */
    public function stopFunnel(Organization $organization, CrmIntegration $integration, Request $request)
    {
        if ($integration->organization_id !== $organization->id || $integration->type !== 'salebot') {
            abort(403);
        }

        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'funnel_id' => 'nullable|string',
        ]);

        $conversation = Conversation::find($request->conversation_id);
        
        // Проверяем доступ
        if ($conversation->bot->organization_id !== $organization->id) {
            abort(403);
        }

        try {
            $clientId = $conversation->metadata['salebot_client_id'] ?? null;
            
            if (!$clientId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Client not found in Salebot',
                ], 404);
            }

            $provider = new SalebotProvider($integration);
            $success = $provider->stopFunnel($clientId, $request->funnel_id);
            
            return response()->json(['success' => $success]);
        } catch (\Exception $e) {
            Log::error('Failed to stop Salebot funnel', [
                'conversation_id' => $request->conversation_id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Передать клиента оператору
     */
    public function transferToOperator(Organization $organization, CrmIntegration $integration, Request $request)
    {
        if ($integration->organization_id !== $organization->id || $integration->type !== 'salebot') {
            abort(403);
        }

        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'operator_id' => 'nullable|string',
        ]);

        $conversation = Conversation::find($request->conversation_id);
        
        // Проверяем доступ
        if ($conversation->bot->organization_id !== $organization->id) {
            abort(403);
        }

        try {
            $clientId = $conversation->metadata['salebot_client_id'] ?? null;
            
            if (!$clientId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Client not found in Salebot',
                ], 404);
            }

            $provider = new SalebotProvider($integration);
            $success = $provider->transferToOperator($clientId, $request->operator_id);
            
            if ($success) {
                // Обновляем статус диалога
                $conversation->update(['status' => 'waiting_operator']);
            }
            
            return response()->json(['success' => $success]);
        } catch (\Exception $e) {
            Log::error('Failed to transfer to operator', [
                'conversation_id' => $request->conversation_id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Отправить рассылку через Salebot
     */
    public function broadcast(Organization $organization, CrmIntegration $integration, Request $request)
    {
        if ($integration->organization_id !== $organization->id || $integration->type !== 'salebot') {
            abort(403);
        }

        $request->validate([
            'message' => 'required|string|max:4000',
            'filters' => 'nullable|array',
            'buttons' => 'nullable|array',
            'delay' => 'nullable|integer|min:0|max:86400',
        ]);

        try {
            $provider = new SalebotProvider($integration);
            
            $result = $provider->broadcastMessage(
                $request->message,
                $request->filters ?? [],
                [
                    'buttons' => $request->buttons ?? [],
                    'delay' => $request->delay ?? 0,
                ]
            );
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to send Salebot broadcast', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить статистику воронок
     */
    public function getFunnelStats(Organization $organization, CrmIntegration $integration, Request $request)
    {
        if ($integration->organization_id !== $organization->id || $integration->type !== 'salebot') {
            abort(403);
        }

        $request->validate([
            'funnel_id' => 'nullable|string',
        ]);

        try {
            $provider = new SalebotProvider($integration);
            $stats = $provider->getFunnelStats($request->funnel_id);
            
            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get Salebot funnel stats', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Создать или обновить переменную
     */
    public function createVariable(Organization $organization, CrmIntegration $integration, Request $request)
    {
        if ($integration->organization_id !== $organization->id || $integration->type !== 'salebot') {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:string,number,boolean,date',
            'default_value' => 'nullable',
        ]);

        try {
            $provider = new SalebotProvider($integration);
            
            $success = $provider->createVariable(
                $request->name,
                $request->type,
                $request->default_value
            );
            
            return response()->json(['success' => $success]);
        } catch (\Exception $e) {
            Log::error('Failed to create Salebot variable', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить список ботов Salebot
     */
    public function getBots(Organization $organization, CrmIntegration $integration)
    {
        if ($integration->organization_id !== $organization->id || $integration->type !== 'salebot') {
            abort(403);
        }

        try {
            $provider = new SalebotProvider($integration);
            $bots = $provider->getBots();
            
            return response()->json([
                'success' => true,
                'bots' => $bots,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get Salebot bots', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Синхронизировать переменные клиента
     */
    public function syncClientVariables(Organization $organization, CrmIntegration $integration, Request $request)
    {
        if ($integration->organization_id !== $organization->id || $integration->type !== 'salebot') {
            abort(403);
        }

        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'variables' => 'required|array',
        ]);

        $conversation = Conversation::find($request->conversation_id);
        
        // Проверяем доступ
        if ($conversation->bot->organization_id !== $organization->id) {
            abort(403);
        }

        try {
            $clientId = $conversation->metadata['salebot_client_id'] ?? null;
            
            if (!$clientId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Client not found in Salebot',
                ], 404);
            }

            $provider = new SalebotProvider($integration);
            $result = $provider->updateLead($clientId, $request->variables);
            
            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync Salebot client variables', [
                'conversation_id' => $request->conversation_id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}