<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\BotFunction;
use App\Models\Organization;
use App\Services\FunctionExecutionService;
use Illuminate\Http\Request;

class BotFunctionController extends Controller
{
    protected FunctionExecutionService $executionService;
    
    public function __construct(FunctionExecutionService $executionService)
    {
        $this->executionService = $executionService;
    }
    
    public function index(Organization $organization, Bot $bot)
    {
        $functions = $bot->functions()->with(['parameters', 'actions', 'behavior'])->get();
        
        return view('functions.index', compact('organization', 'bot', 'functions'));
    }
    
    public function create(Organization $organization, Bot $bot)
    {
        // Получаем доступные CRM интеграции для выбора действий
        $crmIntegrations = $bot->crmIntegrations()->where('is_active', true)->get();
        
        return view('functions.create', compact('organization', 'bot', 'crmIntegrations'));
    }
    
    public function store(Request $request, Organization $organization, Bot $bot)
    {
        $validated = $request->validate([
            'name' => 'required|string|regex:/^[a-z_]+$/|max:50',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|in:auto,manual,keyword',
            'trigger_keywords' => 'nullable|array',
            
            // Параметры
            'parameters' => 'nullable|array',
            'parameters.*.code' => 'required|string|regex:/^[a-z_]+$/',
            'parameters.*.name' => 'required|string',
            'parameters.*.type' => 'required|in:string,number,boolean,date',
            'parameters.*.description' => 'nullable|string',
            'parameters.*.is_required' => 'boolean',
            
            // Действия
            'actions' => 'required|array|min:1',
            'actions.*.type' => 'required|string',
            'actions.*.provider' => 'required|string',
            'actions.*.config' => 'required|array',
            
            // Поведение
            'behavior.on_success' => 'required|in:continue,pause,enhance_prompt',
            'behavior.on_error' => 'required|in:continue,pause,notify',
            'behavior.success_message' => 'nullable|string',
            'behavior.error_message' => 'nullable|string',
            'behavior.prompt_enhancement' => 'nullable|string',
        ]);
        
        DB::beginTransaction();
        
        try {
            // Создаем функцию
            $function = $bot->functions()->create([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? null,
                'trigger_type' => $validated['trigger_type'],
                'trigger_keywords' => $validated['trigger_keywords'] ?? [],
                'is_active' => true,
            ]);
            
            // Создаем параметры
            if (!empty($validated['parameters'])) {
                foreach ($validated['parameters'] as $index => $paramData) {
                    $function->parameters()->create([
                        'code' => $paramData['code'],
                        'name' => $paramData['name'],
                        'type' => $paramData['type'],
                        'description' => $paramData['description'] ?? null,
                        'is_required' => $paramData['is_required'] ?? false,
                        'position' => $index,
                    ]);
                }
            }
            
            // Создаем действия
            foreach ($validated['actions'] as $index => $actionData) {
                $function->actions()->create([
                    'type' => $actionData['type'],
                    'provider' => $actionData['provider'],
                    'config' => $actionData['config'],
                    'field_mapping' => $actionData['field_mapping'] ?? [],
                    'position' => $index,
                ]);
            }
            
            // Создаем поведение
            $function->behavior()->create($validated['behavior']);
            
            DB::commit();
            
            return redirect()
                ->route('functions.show', [$organization, $bot, $function])
                ->with('success', 'Функция успешно создана');
            
        } catch (\Exception $e) {
            DB::rollback();
            
            return back()
                ->withInput()
                ->with('error', 'Ошибка при создании функции: ' . $e->getMessage());
        }
    }
    
    public function test(Request $request, Organization $organization, Bot $bot, BotFunction $function)
    {
        // Тестовое выполнение функции
        $conversation = $bot->conversations()->latest()->first();
        
        if (!$conversation) {
            return back()->with('error', 'Нет доступных диалогов для тестирования');
        }
        
        $message = $conversation->messages()->where('role', 'user')->latest()->first();
        
        if (!$message) {
            return back()->with('error', 'Нет сообщений пользователя для тестирования');
        }
        
        try {
            $execution = $this->executionService->executeFunction($function, $message, $conversation);
            
            return back()->with('success', 'Функция выполнена успешно. ID выполнения: ' . $execution->id);
        } catch (\Exception $e) {
            return back()->with('error', 'Ошибка выполнения: ' . $e->getMessage());
        }
    }
}