<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\BotFunction;
use App\Models\Organization;
use App\Services\FunctionExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $crmIntegrations = $bot->crmIntegrations()->where('crm_integrations.is_active', 1)->get();
        
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
            'actions.*.config' => 'nullable|array',
            'actions.*.field_mapping' => 'nullable|array',
            'actions.*.field_mapping.*.crm_field' => 'nullable|string',
            'actions.*.field_mapping.*.source_type' => 'nullable|string',
            'actions.*.field_mapping.*.value' => 'nullable|string',
            
            // Поведение
            'behavior.on_success' => 'required|in:continue,pause,enhance_prompt',
            'behavior.on_error' => 'required|in:continue,pause,notify',
            'behavior.success_message' => 'nullable|string',
            'behavior.error_message' => 'nullable|string',
            'behavior.prompt_enhancement' => 'nullable|string',
            'behavior.accumulate_parameters' => 'boolean',
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
            
            // Создаем действия с маппингом полей
            foreach ($validated['actions'] as $index => $actionData) {
                // Очищаем пустые маппинги
                $fieldMapping = [];
                if (!empty($actionData['field_mapping'])) {
                    foreach ($actionData['field_mapping'] as $mapping) {
                        if (!empty($mapping['crm_field'])) {
                            $fieldMapping[] = [
                                'crm_field' => $mapping['crm_field'],
                                'source_type' => $mapping['source_type'] ?? 'static',
                                'value' => $mapping['value'] ?? ''
                            ];
                        }
                    }
                }
                
                $function->actions()->create([
                    'type' => $actionData['type'],
                    'provider' => $actionData['provider'],
                    'config' => $actionData['config'] ?? [],
                    'field_mapping' => $fieldMapping,
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

    public function show(Organization $organization, Bot $bot, BotFunction $function)
    {
        // Проверяем, что функция принадлежит боту
        if ($function->bot_id !== $bot->id) {
            abort(404);
        }
        
        $function->load(['parameters', 'actions', 'behavior']);
        
        return view('functions.show', compact('organization', 'bot', 'function'));
    }
    
    public function edit(Organization $organization, Bot $bot, BotFunction $function)
    {
        // Проверяем, что функция принадлежит боту
        if ($function->bot_id !== $bot->id) {
            abort(404);
        }
        
        $function->load(['parameters', 'actions', 'behavior']);
        
        // Получаем доступные CRM интеграции
        $crmIntegrations = $bot->crmIntegrations()->where('crm_integrations.is_active', 1)->get();
        
        return view('functions.edit', compact('organization', 'bot', 'function', 'crmIntegrations'));
    }
    
    public function update(Request $request, Organization $organization, Bot $bot, BotFunction $function)
    {
        // Проверяем, что функция принадлежит боту
        if ($function->bot_id !== $bot->id) {
            abort(404);
        }
        
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
            
            // Действия - исправляем структуру валидации
            'actions' => 'required|array|min:1',
            'actions.*.type' => 'required|string',
            'actions.*.provider' => 'required|string',
            'actions.*.config' => 'nullable|array',
            // Изменяем путь для field_mappings
            'actions.*.field_mapping' => 'nullable|array', // Или используем этот путь
            'actions.*.field_mapping.*.crm_field' => 'nullable|string',
            'actions.*.field_mapping.*.source_type' => 'nullable|string',
            'actions.*.field_mapping.*.value' => 'nullable|string',
            
            // Поведение
            'behavior.on_success' => 'required|in:continue,pause,enhance_prompt',
            'behavior.on_error' => 'required|in:continue,pause,notify',
            'behavior.success_message' => 'nullable|string',
            'behavior.error_message' => 'nullable|string',
            'behavior.prompt_enhancement' => 'nullable|string',
            'behavior.accumulate_parameters' => 'boolean',
        ]);
        
        DB::beginTransaction();
        
        try {
            // Обновляем функцию
            $function->update([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? null,
                'trigger_type' => $validated['trigger_type'],
                'trigger_keywords' => $validated['trigger_keywords'] ?? [],
            ]);
            
            // Удаляем старые параметры и создаем новые
            $function->parameters()->delete();
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
            
            // Удаляем старые действия и создаем новые
            $function->actions()->delete();
            foreach ($validated['actions'] as $index => $actionData) {
                // Обрабатываем field_mapping правильно
                $fieldMapping = [];
                
                // Проверяем оба возможных места для маппинга
                if (!empty($actionData['field_mapping'])) {
                    $fieldMapping = $actionData['field_mapping'];
                } elseif (!empty($actionData['config']['field_mappings'])) {
                    $fieldMapping = $actionData['config']['field_mappings'];
                }
                
                // Очищаем пустые маппинги
                $cleanFieldMapping = [];
                foreach ($fieldMapping as $mapping) {
                    if (!empty($mapping['crm_field'])) {
                        $cleanFieldMapping[] = [
                            'crm_field' => $mapping['crm_field'],
                            'source_type' => $mapping['source_type'] ?? 'static',
                            'value' => $mapping['value'] ?? ''
                        ];
                    }
                }
                
                // Удаляем field_mappings из config если он там есть
                $config = $actionData['config'] ?? [];
                unset($config['field_mappings']);
                
                $function->actions()->create([
                    'type' => $actionData['type'],
                    'provider' => $actionData['provider'],
                    'config' => $config,
                    'field_mapping' => $cleanFieldMapping,
                    'position' => $index,
                ]);
            }
            
            // Обновляем поведение
            $function->behavior()->updateOrCreate(
                ['function_id' => $function->id],
                $validated['behavior']
            );
            
            DB::commit();
            
            return redirect()
                ->route('functions.show', [$organization, $bot, $function])
                ->with('success', 'Функция успешно обновлена');
            
        } catch (\Exception $e) {
            DB::rollback();
            
            return back()
                ->withInput()
                ->with('error', 'Ошибка при обновлении функции: ' . $e->getMessage());
        }
    }
    
    public function destroy(Organization $organization, Bot $bot, BotFunction $function)
    {
        // Проверяем, что функция принадлежит боту
        if ($function->bot_id !== $bot->id) {
            abort(404);
        }
        
        try {
            $function->delete();
            
            return redirect()
                ->route('functions.index', [$organization, $bot])
                ->with('success', 'Функция успешно удалена');
            
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Ошибка при удалении функции: ' . $e->getMessage());
        }
    }


    
    public function test(Organization $organization, Bot $bot, BotFunction $function)
    {
        if ($function->bot_id !== $bot->id) {
            abort(404);
        }
        
        $function->load(['parameters', 'actions', 'behavior']);
        
        return view('functions.test', compact('organization', 'bot', 'function'));
    }

    public function executions(Organization $organization, Bot $bot, BotFunction $function)
    {
        // Проверяем, что функция принадлежит боту
        if ($function->bot_id !== $bot->id) {
            abort(404);
        }
        
        $executions = $function->executions()
            ->with(['conversation'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return view('functions.executions', compact('organization', 'bot', 'function', 'executions'));
    }
}