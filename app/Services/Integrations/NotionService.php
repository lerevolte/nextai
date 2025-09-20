<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Log;
use App\Models\KnowledgeSource;
use App\Models\KnowledgeItem;
use Notion\Databases\Database;
use Notion\Notion;
use Notion\Pages\Page;
use Notion\Blocks\BlockInterface as Block;
use Notion\Blocks\BlockType;
use Notion\Common\RichText;
use Notion\Pages\Properties\PropertyType;

class NotionService
{
    protected ?Notion $client = null;

    public function connect(array $config): bool
    {
        try {
            // Добавляем дефисы в ID базы данных, если их нет
            $config['database_id'] = $this->formatDatabaseId($config['database_id']);

            $this->client = Notion::create($config['api_token']);
            // Проверяем подключение
            $this->client->users()->me();
            return true;
        } catch (\Exception $e) {
            Log::error('Notion connection failed: ' . $e->getMessage());
            return false;
        }
    }

    public function syncDatabase(KnowledgeSource $source, ?int $userId = null): array
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
            
            $databaseId = $this->formatDatabaseId($config['database_id']);
            $database = $this->client->databases()->find($databaseId);
            
            $pages = $this->client->databases()->queryAllPages($database);

            foreach ($pages as $page) {
                try {
                    $externalId = $page->id;
                    $processedIds[] = $externalId;

                    $title = $page->title()?->toString() ?? 'Untitled';
                    // Теперь контент формируется из свойств и блоков внутри страницы
                    $content = $this->formatContentFromPage($page);
                    
                    Log::info("Processing page: {$title} ({$externalId})");

                    // Пропускаем только если нет ни заголовка, ни контента
                    if (($title === 'Untitled' || empty($title)) && empty($content)) {
                        Log::warning("Page with ID '{$externalId}' is empty. Skipping.");
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
                            Log::info("Updating page: {$title}");
                            $this->saveVersion($item, $userId);
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
                        Log::info("Adding new page: {$title}");
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

    /**
     * Формирует единый контент из свойств и блоков страницы.
     */
    protected function formatContentFromPage(Page $page): string
    {
        $properties = $this->extractProperties($page);
        $propertiesText = "";
        foreach ($properties as $name => $value) {
            // Пропускаем title, т.к. он идет отдельно
            if (strtolower($name) === 'title' || strtolower($name) === 'название') {
                continue;
            }

            // Обрабатываем разные типы значений, чтобы пропустить только действительно пустые
            $isTrulyEmpty = $value === null || $value === '' || $value === [];
            
            if (!$isTrulyEmpty) {
                 if (is_array($value)) {
                    $value = implode(', ', $value);
                } elseif (is_bool($value)) {
                    $value = $value ? 'Да' : 'Нет';
                }
                $propertiesText .= "{$name}: {$value}\n";
            }
        }

        $blockContent = $this->extractPageContent($page);

        return trim($propertiesText . "\n\n" . $blockContent);
    }

    protected function extractPageContent(Page $page): string
    {
        $allBlocks = $this->client->blocks()->findChildrenRecursive($page->id);

        $content = '';
        foreach ($allBlocks as $block) {
            $content .= $this->parseBlock($block) . "\n\n";
        }

        return trim($content);
    }

    protected function parseBlock(Block $block): string
    {
        // ... (код parseBlock остается без изменений) ...
        switch ($block->metadata()->type->value) {
            case BlockType::Paragraph->value:
                return $block->toString();
            case BlockType::Heading1->value:
                return "# " . $block->toString();
            case BlockType::Heading2->value:
                return "## " . $block->toString();
            case BlockType::Heading3->value:
                return "### " . $block->toString();
            case BlockType::BulletedListItem->value:
                return "- " . $block->toString();
            case BlockType::NumberedListItem->value:
                return "1. " . $block->toString();
            case BlockType::Quote->value:
                return "> " . $block->toString();
            case BlockType::Code->value:
                return "```" . $block->language->value . "\n" . $block->toString() . "\n```";
            case BlockType::Table->value:
                return $this->parseTable($block);
            default:
                Log::warning("Unsupported block type: " . $block->metadata()->type->value);
                return '';
        }
    }

    protected function parseTable(Block $tableBlock): string
    {
        // ... (код parseTable остается без изменений) ...
        $allRows = $this->client->blocks()->findChildren($tableBlock->metadata()->id);

        $markdown = '';
        foreach ($allRows as $index => $row) {
            if ($row->metadata()->type->value === BlockType::TableRow->value) {
                $cellsText = array_map(fn($cell) => RichText::multipleToString(...$cell), $row->cells);
                $markdown .= '| ' . implode(' | ', $cellsText) . " |\n";

                if ($index === 0 && isset($tableBlock->hasColumnHeader) && $tableBlock->hasColumnHeader) {
                    $markdown .= '| ' . str_repeat('--- | ', count($row->cells)) . "\n";
                }
            }
        }
        
        return $markdown;
    }

    protected function extractProperties(Page $page): array
    {
        // ... (код extractProperties остается без изменений) ...
        $properties = [];
        foreach ($page->properties as $name => $property) {
            switch ($property->metadata()->type->value) {
                case PropertyType::Title->value:
                case PropertyType::RichText->value:
                    $properties[$name] = $property->toString();
                    break;
                case PropertyType::Select->value:
                    $properties[$name] = $property->option?->name;
                    break;
                case PropertyType::MultiSelect->value:
                    $properties[$name] = array_map(fn($option) => $option->name, $property->options);
                    break;
                case PropertyType::Checkbox->value:
                    $properties[$name] = $property->checked;
                    break;
                case PropertyType::Number->value:
                    $properties[$name] = $property->number;
                    break;
                case PropertyType::Date->value:
                    $properties[$name] = $property->start()?->format('Y-m-d');
                    break;
                default:
                    Log::warning("Unsupported property type: " . $property->metadata()->type->value);
                    break;
            }
        }
        return $properties;
    }

    protected function hasContentChanged(KnowledgeItem $item, string $newContent): bool
    {
        return md5($item->content) !== md5($newContent);
    }

    protected function saveVersion(KnowledgeItem $item, ?int $userId = null): void
    {
        $item->versions()->create([
            'version' => $item->version,
            'title' => $item->title,
            'content' => $item->content,
            'embedding' => $item->embedding,
            'metadata' => $item->sync_metadata,
            'created_by' => $userId, // Используем переданный ID пользователя
            'change_notes' => 'Автоматическая синхронизация из Notion',
        ]);
    }

    protected function generateEmbedding(KnowledgeItem $item): void
    {
        // ... (код generateEmbedding остается без изменений) ...
        dispatch(function () use ($item) {
            $embeddingService = app(\App\Services\EmbeddingService::class);
            $text = $item->title . "\n\n" . $item->content;
            $embedding = $embeddingService->generateEmbedding($text);
            
            if ($embedding) {
                $item->update(['embedding' => json_encode($embedding)]);
            }
        })->afterResponse();
    }

    /**
     * Notion API требует ID в формате UUID с дефисами.
     * Эта функция добавляет их, если они отсутствуют.
     */
    private function formatDatabaseId(string $id): string
    {
        $id = str_replace('-', '', $id);
        if (strlen($id) !== 32) {
            return $id; // Возвращаем как есть, если длина некорректна
        }
        return substr($id, 0, 8) . '-' . substr($id, 8, 4) . '-' . substr($id, 12, 4) . '-' . substr($id, 16, 4) . '-' . substr($id, 20, 12);
    }
}