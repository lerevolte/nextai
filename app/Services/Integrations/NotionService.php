<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Log;
use App\Models\KnowledgeSource;
use App\Models\KnowledgeItem;
use Notion\Databases\Database;
use Notion\Databases\Query;
use Notion\Notion;
use Notion\Pages\Page;
use Notion\Blocks\BlockInterface as Block;
use Notion\Common\RichText;

class NotionService
{
    protected ?Notion $client = null;

    public function connect(array $config): bool
    {
        try {
            $this->client = Notion::create($config['api_token']);
            // Проверяем подключение, запрашивая информацию о текущем боте (токене)
            $this->client->users()->me();
            return true;
        } catch (\Exception $e) {
            Log::error('Notion connection failed: ' . $e->getMessage());
            return false;
        }
    }

    public function syncDatabase(KnowledgeSource $source): array
    {
        $config = $source->config;
        if (!$this->connect($config)) {
             throw new \Exception('Failed to connect to Notion.');
        }

        $stats = [
            'added' => 0,
            'updated' => 0,
            'deleted' => 0,
            'errors' => [],
        ];

        try {
            $processedIds = [];
            
            // Метод query() ожидает объект Database, а не строку ID.
            // Сначала найдем базу данных по ее ID.
            $database = $this->client->databases()->find($config['database_id']);
            
            // Используем встроенный метод для получения всех страниц с пагинацией
            $pages = $this->client->databases()->queryAllPages($database);

            foreach ($pages as $page) {
                try {
                    $externalId = $page->id;
                    $processedIds[] = $externalId;

                    $content = $this->extractPageContent($page);
                    $title = $page->title()?->toString() ?? 'Untitled';

                    if (empty($content)) {
                        continue;
                    }

                    $item = KnowledgeItem::where('knowledge_source_id', $source->id)
                        ->where('external_id', $externalId)
                        ->first();

                    $metadata = [
                        'notion_url' => $page->url,
                        'last_edited' => $page->lastEditedTime->format('Y-m-d H:i:s'),
                        'properties' => $this->extractProperties($page),
                    ];

                    if ($item) {
                        if ($this->hasContentChanged($item, $content)) {
                            $this->saveVersion($item);
                            $item->update([
                                'title' => $title,
                                'content' => $content,
                                'version' => $item->version + 1,
                                'last_synced_at' => now(),
                                'sync_metadata' => $metadata,
                            ]);
                            $this->generateEmbedding($item);
                            $stats['updated']++;
                        }
                    } else {
                        $item = KnowledgeItem::create([
                            'knowledge_base_id' => $source->knowledge_base_id,
                            'knowledge_source_id' => $source->id,
                            'type' => 'notion',
                            'title' => $title,
                            'content' => $content,
                            'external_id' => $externalId,
                            'source_url' => $page->url,
                            'is_active' => true,
                            'last_synced_at' => now(),
                            'sync_metadata' => $metadata,
                        ]);
                        $this->generateEmbedding($item);
                        $stats['added']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors'][] = [
                        'page_id' => $page->id ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Failed to sync Notion page', [
                        'page_id' => $page->id ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

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
        // В данной версии SDK метод findChildren не поддерживает пагинацию и вернет только первые 100 блоков.
        // Для страниц с большим количеством блоков может потребоваться обновление SDK или ручная реализация запросов.
        $allBlocks = $this->client->blocks()->findChildren($page->id);

        $content = '';
        foreach ($allBlocks as $block) {
            $content .= $this->parseBlock($block) . "\n\n";
        }

        return trim($content);
    }

    protected function parseBlock(Block $block): string
    {
        // Большинство блоков имеют метод toString() для получения текстового содержимого.
        switch ($block->metadata()->type) {
            case 'paragraph':
            case 'heading_1':
            case 'heading_2':
            case 'heading_3':
            case 'bulleted_list_item':
            case 'numbered_list_item':
            case 'quote':
                $text = $block->toString();
                if ($block->metadata()->type === BlockType::Heading1) return "# " . $text;
                if ($block->metadata()->type === BlockType::Heading2) return "## " . $text;
                if ($block->metadata()->type === BlockType::Heading3) return "### " . $text;
                if ($block->metadata()->type === BlockType::BulletedListItem) return "- " . $text;
                if ($block->metadata()->type === BlockType::NumberedListItem) return "1. " . $text;
                if ($block->metadata()->type === BlockType::Quote) return "> " . $text;
                return $text;
            case 'code':
                return "```" . $block->language->value . "\n" . $block->toString() . "\n```";
            case 'table':
                return $this->parseTable($block);
            default:
                return '';
        }
    }

    protected function parseTable(Block $tableBlock): string
    {
        // Метод findChildren вернет только первые 100 строк таблицы.
        $allRows = $this->client->blocks()->findChildren($tableBlock->metadata()->id);

        $markdown = '';
        foreach ($allRows as $index => $row) {
            if ($row->metadata()->type === 'table_row') {
                $cellsText = array_map(fn($cell) => RichText::multipleToString(...$cell), $row->cells);
                $markdown .= '| ' . implode(' | ', $cellsText) . " |\n";

                if ($index === 0 && $tableBlock->hasColumnHeader) {
                    $markdown .= '| ' . str_repeat('--- | ', count($row->cells)) . "\n";
                }
            }
        }
        
        return $markdown;
    }

    protected function extractProperties(Page $page): array
    {
        $properties = [];
        foreach ($page->properties as $name => $property) {
            switch ($property->metadata()->type) {
                case 'title':
                case 'rich_text':
                    $properties[$name] = $property->toString();
                    break;
                case 'select':
                    $properties[$name] = $property->option?->name;
                    break;
                case 'multi_select':
                    $properties[$name] = array_map(fn($option) => $option->name, $property->options);
                    break;
                case 'checkbox':
                    $properties[$name] = $property->checked;
                    break;
                case 'number':
                    $properties[$name] = $property->number;
                    break;
                case 'date':
                    $properties[$name] = $property->start()?->format('Y-m-d');
                    break;
            }
        }
        return $properties;
    }

    protected function hasContentChanged(KnowledgeItem $item, string $newContent): bool
    {
        return md5($item->content) !== md5($newContent);
    }

    protected function saveVersion(KnowledgeItem $item): void
    {
        $item->versions()->create([
            'version' => $item->version,
            'title' => $item->title,
            'content' => $item->content,
            'embedding' => $item->embedding,
            'metadata' => $item->sync_metadata,
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

