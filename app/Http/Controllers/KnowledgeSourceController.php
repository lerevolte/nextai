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
            'type' => 'required|in:notion,url,google_drive,github',
            'name' => 'required|string|max:255',
        ]);

        $config = [];
        $syncSettings = [];

        // Валидация специфичная для типа источника
        switch ($validated['type']) {
            case 'notion':
                $config = $request->validate([
                    'config.api_token' => 'required|string',
                    'config.database_id' => 'required|string',
                    'config.delete_removed' => 'boolean',
                ]);
                break;
                
            case 'url':
                $config = $request->validate([
                    'config.urls' => 'required|array',
                    'config.urls.*' => 'required|url',
                ]);
                break;
                
            case 'google_drive':
                $config = $request->validate([
                    'config.folder_id' => 'required|string',
                    'config.credentials' => 'required|string',
                ]);
                break;
                
            case 'github':
                $config = $request->validate([
                    'config.repository' => 'required|string',
                    'config.branch' => 'required|string',
                    'config.path' => 'nullable|string',
                    'config.token' => 'nullable|string',
                ]);
                break;
        }

        // Настройки синхронизации
        $syncSettings = $request->validate([
            'sync_settings.interval' => 'required|in:hourly,daily,weekly,monthly,manual',
            'sync_settings.auto_sync' => 'boolean',
        ]);

        $source = KnowledgeSource::create([
            'knowledge_base_id' => $knowledgeBase->id,
            'type' => $validated['type'],
            'name' => $validated['name'],
            'config' => $config['config'],
            'sync_settings' => $syncSettings['sync_settings'],
            'is_active' => true,
            'next_sync_at' => $this->calculateNextSyncTime($syncSettings['sync_settings']['interval']),
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

    public function sync(Organization $organization, Bot $bot, KnowledgeSource $source)
    {
        if ($source->knowledge_base_id !== $bot->knowledgeBase->id) {
            abort(403);
        }

        // Передаем ID пользователя, который инициировал синхронизацию
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

        // Удаляем связанные элементы
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
            'file' => 'required|file|mimes:pdf,doc,docx,txt,md,html,csv,xls,xlsx|max:20480', // 20MB
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
        switch ($interval) {
            case 'hourly':
                return now()->addHour();
            case 'daily':
                return now()->addDay();
            case 'weekly':
                return now()->addWeek();
            case 'monthly':
                return now()->addMonth();
            default:
                return now()->addDay();
        }
    }

    public function logs(Organization $organization, Bot $bot, KnowledgeSource $source)
    {
        $logs = $source->syncLogs()->latest()->paginate(20);

        return view('knowledge.sources.logs', compact('organization', 'bot', 'source', 'logs'));
    }
}