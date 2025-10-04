<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TriggerMatchingService;
use App\Services\FunctionExecutionService;
use Illuminate\Http\Request;

class FunctionTestController extends Controller
{
    protected TriggerMatchingService $triggerService;
    protected FunctionExecutionService $executionService;
    
    public function __construct(
        TriggerMatchingService $triggerService,
        FunctionExecutionService $executionService
    ) {
        $this->triggerService = $triggerService;
        $this->executionService = $executionService;
    }
    
    public function testTriggers(Request $request)
    {
        $functionData = $request->input('function');
        $message = $request->input('message');
        
        // Симулируем проверку триггеров
        // Здесь нужна логика проверки триггеров на основе переданных данных
        
        return response()->json([
            'matched' => true,
            'trigger' => 'test_trigger',
            'parameters' => [
                'test_param' => 'test_value'
            ]
        ]);
    }
    
    public function testExecute(Request $request)
    {
        $functionData = $request->input('function');
        $parameters = $request->input('parameters');
        
        // Симулируем выполнение функции
        // Здесь нужна логика выполнения действий на основе переданных данных
        
        return response()->json([
            'status' => 'success',
            'extractedParams' => $parameters,
            'executedActions' => [
                [
                    'name' => 'Test Action',
                    'status' => 'success',
                    'result' => 'Action completed'
                ]
            ],
            'executionLog' => [
                [
                    'time' => now()->format('H:i:s'),
                    'level' => 'info',
                    'message' => 'Function executed successfully'
                ]
            ]
        ]);
    }
}