<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeItem;
use App\Models\KnowledgeItemVersion;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\EmbeddingService;

class KnowledgeBaseController extends Controller
{
    public function index(Organization $organization, Bot $bot)
    {
        // Получаем или создаем базу знаний для бота
        $knowledgeBase = $bot->knowledgeBase()->firstOrCreate(
            ['bot_id' => $bot->id],
            [
                'name' => 'База знаний ' . $bot->name,
                'description' => 'Основная база знаний бота',
                'is_active' => true,
            ]
        );

        $items = $knowledgeBase->items()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('knowledge.index', compact('organization', 'bot', 'knowledgeBase', 'items'));
    }

    public function create(Organization $organization, Bot $bot)
    {
        $knowledgeBase = $bot->knowledgeBase()->firstOrCreate(
            ['bot_id' => $bot->id],
            [
                'name' => 'База знаний ' . $bot->name,
                'description' => 'Основная база знаний бота',
                'is_active' => true,
            ]
        );

        return view('knowledge.create', compact('organization', 'bot', 'knowledgeBase'));
    }

    public function store(Request $request, Organization $organization, Bot $bot)
    {
        $validated = $request->validate([
            'type' => 'required|in:manual,url,file',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'source_url' => 'nullable|url',
        ]);

        $knowledgeBase = $bot->knowledgeBase()->firstOrCreate(
            ['bot_id' => $bot->id],
            [
                'name' => 'База знаний ' . $bot->name,
                'description' => 'Основная база знаний бота',
                'is_active' => true,
            ]
        );

        $item = $knowledgeBase->items()->create([
            'type' => $validated['type'],
            'title' => $validated['title'],
            'content' => $validated['content'],
            'source_url' => $validated['source_url'] ?? null,
            'is_active' => true,
            'metadata' => [
                'created_by' => auth()->id(),
                'char_count' => mb_strlen($validated['content']),
                'word_count' => str_word_count($validated['content']),
            ],
        ]);

        // Генерируем эмбеддинг в фоне
        dispatch(function () use ($item) {
            $embeddingService = app(EmbeddingService::class);
            $text = $item->title . "\n\n" . $item->content;
            $embedding = $embeddingService->generateEmbedding($text);
            
            if ($embedding) {
                $item->update(['embedding' => json_encode($embedding)]);
            }
        })->afterResponse();

        return redirect()
            ->route('knowledge.index', [$organization, $bot])
            ->with('success', 'Материал добавлен в базу знаний. Эмбеддинг генерируется в фоне.');
    }

    public function edit(Organization $organization, Bot $bot, $itemId)
    {
        $knowledgeBase = $bot->knowledgeBase;
        
        if (!$knowledgeBase) {
            abort(404, 'База знаний не найдена');
        }

        $item = $knowledgeBase->items()->findOrFail($itemId);

        return view('knowledge.edit', compact('organization', 'bot', 'knowledgeBase', 'item'));
    }

    public function update(Request $request, Organization $organization, Bot $bot, $itemId)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'source_url' => 'nullable|url',
            'is_active' => 'boolean',
            'change_notes' => 'nullable|string|max:500', // Добавляем поле для заметок
        ]);

        $knowledgeBase = $bot->knowledgeBase;
        
        if (!$knowledgeBase) {
            abort(404, 'База знаний не найдена');
        }

        $item = $knowledgeBase->items()->findOrFail($itemId);

        // Если есть заметки об изменениях, сохраняем их
        if ($request->has('change_notes') && $request->change_notes) {
            // Временно сохраняем заметки для Observer
            $item->temp_change_notes = $request->change_notes;
        }

        $item->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'source_url' => $validated['source_url'] ?? $item->source_url,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()
            ->route('knowledge.index', [$organization, $bot])
            ->with('success', 'Материал обновлен. Версия ' . $item->version . ' сохранена.');
    }

    public function destroy(Organization $organization, Bot $bot, $itemId)
    {
        $knowledgeBase = $bot->knowledgeBase;
        
        if (!$knowledgeBase) {
            abort(404, 'База знаний не найдена');
        }

        $item = $knowledgeBase->items()->findOrFail($itemId);
        $item->delete();

        return redirect()
            ->route('knowledge.index', [$organization, $bot])
            ->with('success', 'Материал удален');
    }

    // Методы для импорта
    public function import(Organization $organization, Bot $bot)
    {
        $knowledgeBase = $bot->knowledgeBase()->firstOrCreate(
            ['bot_id' => $bot->id],
            [
                'name' => 'База знаний ' . $bot->name,
                'description' => 'Основная база знаний бота',
                'is_active' => true,
            ]
        );

        return view('knowledge.import', compact('organization', 'bot', 'knowledgeBase'));
    }

    public function processImport(Request $request, Organization $organization, Bot $bot)
    {
        $request->validate([
            'source' => 'required|in:file,url,text',
            'file' => 'required_if:source,file|file|mimes:txt,pdf,doc,docx|max:10240',
            'url' => 'required_if:source,url|url',
            'text' => 'required_if:source,text|string',
        ]);

        $knowledgeBase = $bot->knowledgeBase;
        
        if (!$knowledgeBase) {
            abort(404, 'База знаний не найдена');
        }

        $itemsCreated = 0;

        switch ($request->source) {
            case 'file':
                // Обработка файла
                $file = $request->file('file');
                $content = file_get_contents($file->getRealPath());
                
                // Разбиваем на части если большой файл
                $chunks = $this->splitIntoChunks($content);
                
                foreach ($chunks as $index => $chunk) {
                    $knowledgeBase->items()->create([
                        'type' => 'file',
                        'title' => $file->getClientOriginalName() . ' - Часть ' . ($index + 1),
                        'content' => $chunk,
                        'source_url' => null,
                        'is_active' => true,
                        'metadata' => [
                            'filename' => $file->getClientOriginalName(),
                            'part' => $index + 1,
                            'total_parts' => count($chunks),
                        ],
                    ]);
                    $itemsCreated++;
                }
                break;

            case 'url':
                // Парсинг URL
                try {
                    $content = file_get_contents($request->url);
                    $content = strip_tags($content);
                    $content = preg_replace('/\s+/', ' ', $content);
                    
                    $chunks = $this->splitIntoChunks($content);
                    
                    foreach ($chunks as $index => $chunk) {
                        $knowledgeBase->items()->create([
                            'type' => 'url',
                            'title' => 'Контент с ' . parse_url($request->url, PHP_URL_HOST) . ' - Часть ' . ($index + 1),
                            'content' => $chunk,
                            'source_url' => $request->url,
                            'is_active' => true,
                        ]);
                        $itemsCreated++;
                    }
                } catch (\Exception $e) {
                    return back()->withErrors(['url' => 'Не удалось загрузить содержимое по указанному URL']);
                }
                break;

            case 'text':
                // Прямой ввод текста
                $chunks = $this->splitIntoChunks($request->text);
                
                foreach ($chunks as $index => $chunk) {
                    $knowledgeBase->items()->create([
                        'type' => 'manual',
                        'title' => 'Текстовый блок ' . ($index + 1),
                        'content' => $chunk,
                        'source_url' => null,
                        'is_active' => true,
                    ]);
                    $itemsCreated++;
                }
                break;
        }

        return redirect()
            ->route('knowledge.index', [$organization, $bot])
            ->with('success', "Импортировано элементов: $itemsCreated");
    }

    private function splitIntoChunks($content, $maxLength = 2000)
    {
        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/', $content);
        $currentChunk = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($currentChunk . ' ' . $sentence) <= $maxLength) {
                $currentChunk .= ($currentChunk ? ' ' : '') . $sentence;
            } else {
                if ($currentChunk) {
                    $chunks[] = $currentChunk;
                }
                $currentChunk = $sentence;
            }
        }

        if ($currentChunk) {
            $chunks[] = $currentChunk;
        }

        return $chunks ?: [$content];
    }

    public function versions(Organization $organization, Bot $bot, $itemId)
    {
        $item = KnowledgeItem::findOrFail($itemId);
        
        if ($item->knowledge_base_id !== $bot->knowledgeBase->id) {
            abort(403);
        }
        
        $versions = $item->versions()
            ->with('creator')
            ->orderBy('version', 'desc')
            ->get();
        
        return view('knowledge.versions', compact('organization', 'bot', 'item', 'versions'));
    }

    public function restoreVersion(Request $request, Organization $organization, Bot $bot, $itemId)
    {
        $item = KnowledgeItem::findOrFail($itemId);
        
        if ($item->knowledge_base_id !== $bot->knowledgeBase->id) {
            abort(403);
        }
        
        $version = KnowledgeItemVersion::findOrFail($request->version_id);
        
        if ($version->knowledge_item_id !== $item->id) {
            abort(403);
        }
        
        // Сохраняем текущую версию
        $item->versions()->create([
            'version' => $item->version,
            'title' => $item->title,
            'content' => $item->content,
            'embedding' => $item->embedding,
            'metadata' => $item->metadata,
            'created_by' => auth()->id(),
            'change_notes' => 'Сохранено перед восстановлением версии ' . $version->version,
        ]);
        
        // Восстанавливаем выбранную версию
        $item->update([
            'title' => $version->title,
            'content' => $version->content,
            'embedding' => $version->embedding,
            'metadata' => array_merge($version->metadata ?? [], [
                'restored_from_version' => $version->version,
                'restored_by' => auth()->id(),
                'restored_at' => now(),
            ]),
            'version' => $item->version + 1,
        ]);
        
        return redirect()
            ->route('knowledge.versions', [$organization, $bot, $item->id])
            ->with('success', 'Версия ' . $version->version . ' успешно восстановлена');
    }
}