<?php

namespace App\Services\Integrations;

use Notion\Notion;
use Notion\Pages\Page;
use Notion\Databases\Database;
use Notion\Blocks\BlockInterface;
use Illuminate\Support\Facades\Log;
use App\Models\KnowledgeSource;
use App\Models\KnowledgeItem;
use League\HTMLToMarkdown\HtmlConverter;

class NotionService
{
    protected $client;
    protected HtmlConverter $htmlConverter;

    public function __construct()
    {
        $this->htmlConverter = new HtmlConverter();
    }

    public function connect(array $config): bool
    {
        try {
            $this->client = Notion::create($config['api_token']);
            
            // Проверяем подключение
            $this->client->users()->list();
            
            return true;
        } catch (\Exception $e) {
            Log::error('Notion connection failed: ' . $e->getMessage());
            return false;
        }
    }

    public function syncDatabase(KnowledgeSource $source): array
    {
        $config = $source->config;
        $this->connect($config);
        
        $stats = [
            'added' => 0,
            'updated' => 0,
            'deleted' => 0,
            'errors' => [],
        ];

        try {
            // Получаем страницы из базы данных Notion
            $database = $this->client->databases()->find($config['database_id']);
            $pages = $this->client->databases()->queryAllPages($database);

            $processedIds = [];

            foreach ($pages as $page) {
                try {
                    $externalId = $page->id();
                    $processedIds[] = $externalId;

                    // Получаем контент страницы
                    $content = $this->extractPageContent($page);
                    $title = $this->extractPageTitle($page);
                    
                    if (empty($content)) {
                        continue;
                    }

                    // Проверяем существующий элемент
                    $item = KnowledgeItem::where('knowledge_source_id', $source->id)
                        ->where('external_id', $externalId)
                        ->first();

                    $metadata = [
                        'notion_url' => $page->url(),
                        'last_edited' => $page->lastEditedTime()->format('Y-m-d H:i:s'),
                        'properties' => $this->extractProperties($page),
                    ];

                    if ($item) {
                        // Проверяем изменения
                        if ($this->hasContentChanged($item, $content)) {
                            // Сохраняем версию
                            $this->saveVersion($item);
                            
                            // Обновляем элемент
                            $item->update([
                                'title' => $title,
                                'content' => $content,
                                'version' => $item->version + 1,
                                'last_synced_at' => now(),
                                'sync_metadata' => $metadata,
                            ]);
                            
                            // Генерируем новый эмбеддинг
                            $this->generateEmbedding($item);
                            
                            $stats['updated']++;
                        }
                    } else {
                        // Создаем новый элемент
                        $item = KnowledgeItem::create([
                            'knowledge_base_id' => $source->knowledge_base_id,
                            'knowledge_source_id' => $source->id,
                            'type' => 'notion',
                            'title' => $title,
                            'content' => $content,
                            'external_id' => $externalId,
                            'source_url' => $page->url(),
                            'is_active' => true,
                            'last_synced_at' => now(),
                            'sync_metadata' => $metadata,
                        ]);
                        
                        // Генерируем эмбеддинг
                        $this->generateEmbedding($item);
                        
                        $stats['added']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors'][] = [
                        'page_id' => $page->id(),
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Failed to sync Notion page', [
                        'page_id' => $page->id(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Удаляем элементы, которых больше нет в Notion
            if ($config['delete_removed'] ?? false) {
                $deletedCount = KnowledgeItem::where('knowledge_source_id', $source->id)
                    ->whereNotIn('external_id', $processedIds)
                    ->delete();
                
                $stats['deleted'] = $deletedCount;
            }

        } catch (\Exception $e) {
            Log::error('Notion sync failed', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }

        return $stats;
    }

    protected function extractPageContent(Page $page): string
    {
        $blocks = $this->client->blocks()->children($page->id());
        $content = '';

        foreach ($blocks as $block) {
            $content .= $this->parseBlock($block) . "\n\n";
        }

        return trim($content);
    }

    protected function parseBlock(BlockInterface $block): string
    {
        $type = $block->type();
        
        switch ($type) {
            case 'paragraph':
                return $block->paragraph()->toText();
                
            case 'heading_1':
                return '# ' . $block->heading1()->toText();
                
            case 'heading_2':
                return '## ' . $block->heading2()->toText();
                
            case 'heading_3':
                return '### ' . $block->heading3()->toText();
                
            case 'bulleted_list_item':
                return '- ' . $block->bulletedListItem()->toText();
                
            case 'numbered_list_item':
                return '1. ' . $block->numberedListItem()->toText();
                
            case 'code':
                $code = $block->code();
                return "```" . $code->language() . "\n" . $code->toText() . "\n```";
                
            case 'quote':
                return '> ' . $block->quote()->toText();
                
            case 'table':
                return $this->parseTable($block);
                
            default:
                return '';
        }
    }

    protected function parseTable($tableBlock): string
    {
        // Парсинг таблиц Notion
        $rows = $this->client->blocks()->children($tableBlock->id());
        $markdown = '';
        
        foreach ($rows as $index => $row) {
            if ($row->type() === 'table_row') {
                $cells = $row->tableRow()->cells();
                $markdown .= '| ' . implode(' | ', array_map(function($cell) {
                    return $cell->toText();
                }, $cells)) . " |\n";
                
                // Добавляем разделитель после заголовка
                if ($index === 0) {
                    $markdown .= '| ' . str_repeat('--- | ', count($cells)) . "\n";
                }
            }
        }
        
        return $markdown;
    }

    protected function extractPageTitle(Page $page): string
    {
        $properties = $page->properties();
        
        // Ищем свойство title/name
        foreach ($properties as $property) {
            if ($property->type() === 'title') {
                return $property->title()->toText();
            }
        }
        
        return 'Untitled';
    }

    protected function extractProperties(Page $page): array
    {
        $properties = [];
        
        foreach ($page->properties() as $key => $property) {
            $type = $property->type();
            
            switch ($type) {
                case 'title':
                case 'rich_text':
                    $properties[$key] = $property->toText();
                    break;
                    
                case 'select':
                    $properties[$key] = $property->select()?->name();
                    break;
                    
                case 'multi_select':
                    $properties[$key] = array_map(function($option) {
                        return $option->name();
                    }, $property->multiSelect() ?? []);
                    break;
                    
                case 'checkbox':
                    $properties[$key] = $property->checkbox();
                    break;
                    
                case 'number':
                    $properties[$key] = $property->number();
                    break;
                    
                case 'date':
                    $properties[$key] = $property->date()?->start()?->format('Y-m-d');
                    break;
            }
        }
        
        return $properties;
    }

    protected function hasContentChanged(KnowledgeItem $item, string $newContent): bool
    {
        // Сравниваем хеши контента
        $oldHash = md5($item->content);
        $newHash = md5($newContent);
        
        return $oldHash !== $newHash;
    }

    protected function saveVersion(KnowledgeItem $item): void
    {
        $item->versions()->create([
            'version' => $item->version,
            'title' => $item->title,
            'content' => $item->content,
            'embedding' => $item->embedding,
            'metadata' => $item->metadata,
            'created_by' => auth()->id(),
            'change_notes' => 'Автоматическая синхронизация из Notion',
        ]);
    }

    protected function generateEmbedding(KnowledgeItem $item): void
    {
        dispatch(function () use ($item) {
            $embeddingService = app(\App\Services\EmbeddingService::class);
            $text = $item->title . "\n\n" . $item->content;
            $embedding = $embeddingService->generateEmbedding($text);
            
            if ($embedding) {
                $item->update(['embedding' => json_encode($embedding)]);
            }
        })->afterResponse();
    }
}