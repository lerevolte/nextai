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
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\RendererFactory;
use Jfcherng\Diff\DiffHelper;

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

        // Проверяем, не восстанавливаем ли мы ту же версию
        if ($item->content === $version->content && $item->title === $version->title) {
            return redirect()
                ->route('knowledge.versions', [$organization, $bot, $item->id])
                ->with('info', 'Эта версия уже является текущей');
        }

        // Временно отключаем Observer для этого обновления
        $item->unsetEventDispatcher();

        // Восстанавливаем версию без создания новой
        $item->update([
            'title' => $version->title,
            'content' => $version->content,
            'embedding' => $version->embedding,
            'metadata' => array_merge($version->metadata ?? [], [
                'restored_from_version' => $version->version,
                'restored_by' => auth()->id(),
                'restored_at' => now(),
            ]),
            'version' => $version->version, // Сохраняем номер версии
        ]);

        return redirect()
            ->route('knowledge.versions', [$organization, $bot, $item->id])
            ->with('success', 'Восстановлена версия ' . $version->version);
    }

    public function compareVersions(Request $request, Organization $organization, Bot $bot, $itemId)
    {
        $item = KnowledgeItem::findOrFail($itemId);
        
        if ($item->knowledge_base_id !== $bot->knowledgeBase->id) {
            abort(403);
        }
        
        $fromId = $request->from_id;
        $toId = $request->to_id;
        
        // Получаем контент для сравнения
        if ($fromId === 'current') {
            $fromContent = $item->content;
            $fromTitle = $item->title;
            $fromVersion = 'Текущая версия';
        } else {
            $fromVersion = KnowledgeItemVersion::findOrFail($fromId);
            $fromContent = $fromVersion->content;
            $fromTitle = $fromVersion->title;
            $fromVersion = 'Версия ' . $fromVersion->version;
        }
        
        if ($toId === 'current') {
            $toContent = $item->content;
            $toTitle = $item->title;
            $toVersion = 'Текущая версия';
        } else {
            $toVersion = KnowledgeItemVersion::findOrFail($toId);
            $toContent = $toVersion->content;
            $toTitle = $toVersion->title;
            $toVersion = 'Версия ' . $toVersion->version;
        }
        
        // Генерируем HTML diff
        $titleDiff = $this->generateHtmlDiff($fromTitle, $toTitle);
        $contentDiff = $this->generateHtmlDiff($fromContent, $toContent);
        
        // Статистика изменений
        $stats = $this->calculateDiffStats($fromContent, $toContent);
        
        return response()->json([
            'titleDiff' => $titleDiff,
            'contentDiff' => $contentDiff,
            'stats' => $stats,
            'fromVersion' => $fromVersion,
            'toVersion' => $toVersion,
        ]);
    }

    private function generateHtmlDiff($old, $new)
    {
        // Если библиотека установлена, используем её
        if (class_exists('Jfcherng\Diff\DiffHelper')) {
            $diff = DiffHelper::calculate($old, $new, 'Inline', [
                'context' => 3,
                'ignoreCase' => false,
                'ignoreWhitespace' => false,
            ]);
            return $diff;
        }
        
        // Иначе используем простую реализацию
        return $this->simpleHtmlDiff($old, $new);
    }

    private function simpleHtmlDiff($old, $new)
    {
        // Разбиваем на слова для более точного сравнения
        $oldWords = preg_split('/(\s+|[.!?,;:\-\(\)\[\]{}"])/', $old, -1, PREG_SPLIT_DELIM_CAPTURE);
        $newWords = preg_split('/(\s+|[.!?,;:\-\(\)\[\]{}"])/', $new, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        // Используем алгоритм LCS (Longest Common Subsequence)
        $diff = $this->computeLCS($oldWords, $newWords);
        
        $html = '';
        foreach ($diff as $part) {
            if ($part['type'] === 'unchanged') {
                $html .= htmlspecialchars($part['value']);
            } elseif ($part['type'] === 'deleted') {
                $html .= '<span style="background-color: #fee2e2; color: #991b1b; text-decoration: line-through; padding: 0 2px;">' . 
                         htmlspecialchars($part['value']) . '</span>';
            } elseif ($part['type'] === 'added') {
                $html .= '<span style="background-color: #dcfce7; color: #166534; font-weight: 500; padding: 0 2px;">' . 
                         htmlspecialchars($part['value']) . '</span>';
            }
        }
        
        return $html;
    }

    private function computeLCS($old, $new)
    {
        $m = count($old);
        $n = count($new);
        $lcs = [];
        
        // Построение матрицы LCS
        for ($i = 0; $i <= $m; $i++) {
            $lcs[$i][0] = 0;
        }
        for ($j = 0; $j <= $n; $j++) {
            $lcs[0][$j] = 0;
        }
        
        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($old[$i - 1] === $new[$j - 1]) {
                    $lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
                }
            }
        }
        
        // Восстановление diff
        $diff = [];
        $i = $m;
        $j = $n;
        
        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $old[$i - 1] === $new[$j - 1]) {
                array_unshift($diff, ['type' => 'unchanged', 'value' => $old[$i - 1]]);
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
                array_unshift($diff, ['type' => 'added', 'value' => $new[$j - 1]]);
                $j--;
            } elseif ($i > 0 && ($j === 0 || $lcs[$i - 1][$j] > $lcs[$i][$j - 1])) {
                array_unshift($diff, ['type' => 'deleted', 'value' => $old[$i - 1]]);
                $i--;
            }
        }
        
        // Объединяем последовательные элементы одного типа
        $merged = [];
        $current = null;
        
        foreach ($diff as $part) {
            if ($current && $current['type'] === $part['type']) {
                $current['value'] .= $part['value'];
            } else {
                if ($current) {
                    $merged[] = $current;
                }
                $current = $part;
            }
        }
        if ($current) {
            $merged[] = $current;
        }
        
        return $merged;
    }

    private function calculateDiffStats($old, $new)
    {
        $oldLines = explode("\n", $old);
        $newLines = explode("\n", $new);
        
        $oldWords = str_word_count($old);
        $newWords = str_word_count($new);
        
        $oldChars = strlen($old);
        $newChars = strlen($new);
        
        return [
            'lines' => [
                'added' => max(0, count($newLines) - count($oldLines)),
                'removed' => max(0, count($oldLines) - count($newLines)),
                'total_old' => count($oldLines),
                'total_new' => count($newLines),
            ],
            'words' => [
                'added' => max(0, $newWords - $oldWords),
                'removed' => max(0, $oldWords - $newWords),
                'total_old' => $oldWords,
                'total_new' => $newWords,
            ],
            'chars' => [
                'added' => max(0, $newChars - $oldChars),
                'removed' => max(0, $oldChars - $newChars),
                'total_old' => $oldChars,
                'total_new' => $newChars,
            ],
        ];
    }

    public function deleteVersion(Request $request, Organization $organization, Bot $bot, $itemId, $versionId)
    {
        $item = KnowledgeItem::findOrFail($itemId);
        
        if ($item->knowledge_base_id !== $bot->knowledgeBase->id) {
            abort(403);
        }
        
        $version = KnowledgeItemVersion::where('knowledge_item_id', $item->id)
            ->where('id', $versionId)
            ->firstOrFail();
        
        // Не позволяем удалить последнюю версию
        if ($item->versions()->count() <= 1) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Нельзя удалить единственную версию'], 400);
            }
            
            return redirect()
                ->route('knowledge.versions', [$organization, $bot, $item->id])
                ->with('error', 'Нельзя удалить единственную версию');
        }
        
        $version->delete();
        
        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        
        return redirect()
            ->route('knowledge.versions', [$organization, $bot, $item->id])
            ->with('success', 'Версия удалена');
    }
}