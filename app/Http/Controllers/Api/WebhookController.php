<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebhookTriggerService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    protected WebhookTriggerService $webhookService;
    
    public function __construct(WebhookTriggerService $webhookService)
    {
        $this->webhookService = $webhookService;
    }
    
    public function handle(Request $request, string $key)
    {
        $result = $this->webhookService->handleWebhook($request, $key);
        
        return response()->json($result, $result['success'] ? 200 : 400);
    }
}