<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeSource;
use App\Models\Organization;
use App\Jobs\SyncKnowledgeSource;
use App\Services\Importers\DocumentImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KnowledgeSourceController extends Controller
{
    protected DocumentImporter $documentImporter;

    public function __construct(DocumentImporter $documentImporter)
    {
        $this->documentImporter = $documentImporter;
    }

    public function index(Organization $organization, Bot $bot)
    {
        $knowledgeBase = $bot->knowledgeBase;
        
        if (!$knowledgeBase) {
            return redirect()->route('knowledge.index', [$organization, $bot])
                ->with('error', 'База знаний не найдена');
        }

        $sources = $knowledgeBase->sources()
            ->withCount('items')
            ->with([
                'syncLogs' => function ($query) {
                    $query->latest()->limit(1);
                }
            ])
            ->get();

        return view('knowledge.sources.index', compact('organization', 'bot', 'knowledgeBase', 'sources'));
    }

    public function create(Organization $organization, Bot $bot)
    {
        $knowledgeBase = $bot->knowledgeBase;
        
        if (!$knowledgeBase) {
            return redirect()->route('knowledge.index', [$organization, $bot])
                ->with('error', 'База знаний не найдена');
        }

        return view('knowledge.sources.create', compact('organization', 'bot', 'knowledgeBase'));
    }

    public function store(Request $request, Organization $organization, Bot $bot)
    {
        $knowledgeBase = $bot->knowledgeBase;
        
        if (!$knowledgeBase) {
            return redirect()->route('knowledge.index', [$organization, $bot])
                ->with('error', 'База знаний не найдена');
        }

        $validated = $request->validate([
            'type' => 'required|in:notion,url,google_drive,github,google_docs',
            'name' => 'required|string|max:255',
        ]);

        $config = [];

        // Валидация специфичная для типа источника
        switch ($validated['type']) {
            case 'notion':
                $configValidation = $request->validate([
                    'config.api_token' => 'required|string',
                    'config.database_id' => 'required|string',
                    'config.delete_removed' => 'nullable|boolean',
                ]);
                $config = $configValidation['config'] ?? [];
                $config['delete_removed'] = $request->boolean('config.delete_removed');
                break;
                
            case 'url':
                $configValidation = $request->validate([
                    'config.urls' => 'required|array|min:1',
                    'config.urls.*' => 'required|url',
                ]);
                $config = $configValidation['config'] ?? [];
                break;
                
            case 'google_drive':
                $configValidation = $request->validate([
                    'config.folder_id' => 'required|string',
                    'config.credentials' => 'required|string',
                ]);
                $config = $configValidation['config'] ?? [];
                break;
                
            case 'github':
                $configValidation = $request->validate([
                    'config.repository' => 'required|string',
                    'config.branch' => 'required|string',
                    'config.path' => 'nullable|string',
                    'config.token' => 'nullable|string',
                ]);
                $config = $configValidation['config'] ?? [];
                break;

            case 'google_docs':
                $config = $this->validateGoogleDocsConfig($request);
                break;
        }

        // Настройки синхронизации
        $syncSettingsValidation = $request->validate([
            'sync_settings.interval' => 'required|in:hourly,daily,weekly,monthly,manual',
            'sync_settings.auto_sync' => 'nullable|boolean',
        ]);
        
        $syncSettings = $syncSettingsValidation['sync_settings'] ?? [];
        $syncSettings['auto_sync'] = $request->boolean('sync_settings.auto_sync');

        $source = KnowledgeSource::create([
            'knowledge_base_id' => $knowledgeBase->id,
            'type' => $validated['type'],
            'name' => $validated['name'],
            'config' => $config,
            'sync_settings' => $syncSettings,
            'is_active' => true,
            'next_sync_at' => $this->calculateNextSyncTime($syncSettings['interval'] ?? 'daily'),
        ]);

        // Запускаем первую синхронизацию
        if ($request->boolean('sync_now', false)) {
            SyncKnowledgeSource::dispatch($source, auth()->id());
            
            return redirect()
                ->route('knowledge.sources.index', [$organization, $bot])
                ->with('success', 'Источник добавлен. Синхронизация запущена в фоне.');
        }

        return redirect()
            ->route('knowledge.sources.index', [$organization, $bot])
            ->with('success', 'Источник успешно добавлен');
    }

    /**
     * Валидация конфигурации Google Docs
     */
    protected function validateGoogleDocsConfig(Request $request): array
    {
        $authType = $request->input('config.auth_type', 'public');
        $sourceType = $request->input('config.source_type', 'urls');

        $config = [
            'auth_type' => $authType,
            'source_type' => $sourceType,
            'delete_removed' => $request->boolean('config.delete_removed'),
        ];

        // Валидация авторизации
        switch ($authType) {
            case 'public':
                // Для публичного доступа ничего не нужно
                break;

            case 'service_account':
                $request->validate([
                    'config.service_account_json' => 'required|string',
                ]);
                
                $json = $request->input('config.service_account_json');
                $decoded = json_decode($json, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        response()->json(['errors' => ['config.service_account_json' => ['Невалидный JSON']]], 422)
                    );
                }
                
                if (empty($decoded['type']) || $decoded['type'] !== 'service_account') {
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        response()->json(['errors' => ['config.service_account_json' => ['Это не ключ Service Account']]], 422)
                    );
                }
                
                $config['service_account_json'] = $json;
                break;

            case 'oauth':
                $request->validate([
                    'config.access_token' => 'required|string',
                    'config.refresh_token' => 'nullable|string',
                ]);
                $config['access_token'] = $request->input('config.access_token');
                $config['refresh_token'] = $request->input('config.refresh_token');
                break;

            default:
                throw new \InvalidArgumentException('Неизвестный тип авторизации: ' . $authType);
        }

        // Валидация источника документов
        switch ($sourceType) {
            case 'urls':
                $request->validate([
                    'config.document_urls' => 'required|array|min:1',
                    'config.document_urls.*' => 'required|string',
                ]);
                
                $urls = $request->input('config.document_urls', []);
                $validUrls = [];
                
                foreach ($urls as $url) {
                    $url = trim($url);
                    if (empty($url)) continue;
                    
                    // Проверяем формат URL Google Docs
                    if (!preg_match('/docs\.google\.com\/document\/d\/([a-zA-Z0-9_-]+)/', $url)) {
                        throw new \Illuminate\Validation\ValidationException(
                            validator([], []),
                            response()->json(['errors' => ['config.document_urls' => ["Невалидный URL Google Docs: {$url}"]]], 422)
                        );
                    }
                    
                    $validUrls[] = $url;
                }
                
                $config['document_urls'] = $validUrls;
                break;

            case 'documents':
                $request->validate([
                    'config.document_ids' => 'required|array|min:1',
                    'config.document_ids.*' => 'required|string',
                ]);
                
                $ids = array_filter(array_map('trim', $request->input('config.document_ids', [])));
                $config['document_ids'] = array_values($ids);
                break;

            case 'folder':
                // Папка требует авторизацию
                if ($authType === 'public') {
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        response()->json(['errors' => ['config.source_type' => ['Для синхронизации папки требуется Service Account или OAuth авторизация']]], 422)
                    );
                }
                
                $request->validate([
                    'config.folder_id' => 'required|string',
                ]);
                $config['folder_id'] = trim($request->input('config.folder_id'));
                break;

            default:
                throw new \InvalidArgumentException('Неизвестный тип источника: ' . $sourceType);
        }

        return $config;
    }

    public function sync(Organization $organization, Bot $bot, KnowledgeSource $source)
    {
        if ($source->knowledge_base_id !== $bot->knowledgeBase->id) {
            abort(403);
        }

        SyncKnowledgeSource::dispatch($source, auth()->id());

        return redirect()
            ->route('knowledge.sources.index', [$organization, $bot])
            ->with('success', 'Синхронизация запущена в фоне');
    }

    public function destroy(Organization $organization, Bot $bot, KnowledgeSource $source)
    {
        if ($source->knowledge_base_id !== $bot->knowledgeBase->id) {
            abort(403);
        }

        if (request()->boolean('delete_items', false)) {
            $source->items()->delete();
        }

        $source->delete();

        return redirect()
            ->route('knowledge.sources.index', [$organization, $bot])
            ->with('success', 'Источник удален');
    }

    public function import(Organization $organization, Bot $bot)
    {
        $knowledgeBase = $bot->knowledgeBase;
        
        if (!$knowledgeBase) {
            return redirect()->route('knowledge.index', [$organization, $bot])
                ->with('error', 'База знаний не найдена');
        }

        return view('knowledge.import', compact('organization', 'bot', 'knowledgeBase'));
    }

    public function processImport(Request $request, Organization $organization, Bot $bot)
    {
        $knowledgeBase = $bot->knowledgeBase;
        
        if (!$knowledgeBase) {
            return redirect()->route('knowledge.index', [$organization, $bot])
                ->with('error', 'База знаний не найдена');
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,txt,md,html,csv,xls,xlsx|max:20480',
        ]);

        try {
            $file = $request->file('file');
            $items = $this->documentImporter->import($file, $knowledgeBase);

            return redirect()
                ->route('knowledge.index', [$organization, $bot])
                ->with('success', 'Импортировано элементов: ' . count($items));

        } catch (\Exception $e) {
            Log::error('Document import failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            return back()
                ->with('error', 'Ошибка при импорте: ' . $e->getMessage());
        }
    }

    protected function calculateNextSyncTime(string $interval): \Carbon\Carbon
    {
        return match($interval) {
            'hourly' => now()->addHour(),
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            default => now()->addDay(),
        };
    }

    public function logs(Organization $organization, Bot $bot, KnowledgeSource $source)
    {
        $logs = $source->syncLogs()->latest()->paginate(20);

        return view('knowledge.sources.logs', compact('organization', 'bot', 'source', 'logs'));
    }
}