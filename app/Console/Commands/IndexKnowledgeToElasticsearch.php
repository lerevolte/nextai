<?php

namespace App\Console\Commands;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeItem;
use App\Models\KnowledgeChunk;
use Elastic\Elasticsearch\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IndexKnowledgeToElasticsearch extends Command
{
    protected $signature = 'knowledge:index 
                            {--bot= : ID бота}
                            {--fresh : Удалить существующие чанки и создать заново}
                            {--chunk-size=1500 : Максимальный размер чанка в символах}';
    
    protected $description = 'Разбивает документы на чанки и индексирует в Elasticsearch';

    protected Client $elasticsearch;

    public function __construct(Client $elasticsearch)
    {
        parent::__construct();
        $this->elasticsearch = $elasticsearch;
    }

    public function handle(): int
    {
        $botId = $this->option('bot');
        $fresh = $this->option('fresh');
        $chunkSize = (int) $this->option('chunk-size');

        if (!$botId) {
            $this->error('Укажите --bot=ID');
            return 1;
        }

        $knowledgeBase = KnowledgeBase::where('bot_id', $botId)->first();
        
        if (!$knowledgeBase) {
            $this->error("База знаний для бота ID {$botId} не найдена");
            return 1;
        }

        $this->info("База знаний: {$knowledgeBase->name} (ID: {$knowledgeBase->id})");

        // Создаём индекс в Elasticsearch
        $this->createElasticsearchIndex();

        // Удаляем старые чанки если указан --fresh
        if ($fresh) {
            $this->deleteChunksFromElasticsearch($knowledgeBase->id);
            $deleted = KnowledgeChunk::where('knowledge_base_id', $knowledgeBase->id)->delete();
            $this->warn("Удалено старых чанков: {$deleted}");
        }

        // Получаем документы для индексации
        $items = KnowledgeItem::where('knowledge_base_id', $knowledgeBase->id)
            ->where('is_active', true)
            ->get();

        $this->info("Документов для обработки: " . $items->count());

        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        $stats = ['chunks_created' => 0, 'items_processed' => 0];

        foreach ($items as $item) {
            $chunks = $this->splitIntoChunks($item, $chunkSize);
            
            foreach ($chunks as $index => $chunkData) {
                KnowledgeChunk::updateOrCreate(
                    [
                        'knowledge_item_id' => $item->id,
                        'chunk_index' => $index,
                    ],
                    [
                        'knowledge_base_id' => $knowledgeBase->id,
                        'knowledge_source_id' => $item->knowledge_source_id,
                        'title' => $chunkData['title'],
                        'content' => $chunkData['content'],
                        'source_url' => $item->source_url,
                        'total_chunks' => count($chunks),
                        'is_active' => true,
                        'metadata' => [
                            'original_title' => $item->title,
                            'item_type' => $item->type,
                            'content_length' => strlen($chunkData['content']),
                        ],
                    ]
                );
                
                $stats['chunks_created']++;
            }

            $stats['items_processed']++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Индексируем в Elasticsearch
        $this->info('Индексация в Elasticsearch...');
        $this->indexToElasticsearch($knowledgeBase->id);

        $this->newLine();
        $this->info('Готово!');
        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Обработано документов', $stats['items_processed']],
                ['Создано чанков', $stats['chunks_created']],
            ]
        );

        return 0;
    }

    /**
     * Создаёт индекс в Elasticsearch
     */
    protected function createElasticsearchIndex(): void
    {
        $indexName = 'knowledge_chunks';

        try {
            $exists = $this->elasticsearch->indices()->exists(['index' => $indexName]);
            
            if ($exists->getStatusCode() === 200) {
                $this->info("Индекс '{$indexName}' уже существует");
                return;
            }
        } catch (\Exception $e) {
            // Индекс не существует, создаём
        }

        $this->info("Создание индекса '{$indexName}'...");

        $this->elasticsearch->indices()->create([
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            'russian_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'russian_stemmer', 'russian_stop'],
                            ],
                        ],
                        'filter' => [
                            'russian_stemmer' => [
                                'type' => 'stemmer',
                                'language' => 'russian',
                            ],
                            'russian_stop' => [
                                'type' => 'stop',
                                'stopwords' => '_russian_',
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'knowledge_base_id' => ['type' => 'integer'],
                        'title' => [
                            'type' => 'text',
                            'analyzer' => 'russian_analyzer',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'content' => [
                            'type' => 'text',
                            'analyzer' => 'russian_analyzer',
                        ],
                        'source_url' => ['type' => 'keyword'],
                        'chunk_index' => ['type' => 'integer'],
                        'is_active' => ['type' => 'boolean'],
                        'created_at' => ['type' => 'date'],
                    ],
                ],
            ],
        ]);

        $this->info("Индекс создан успешно");
    }

    /**
     * Удаляет чанки из Elasticsearch
     */
    protected function deleteChunksFromElasticsearch(int $knowledgeBaseId): void
    {
        try {
            $this->elasticsearch->deleteByQuery([
                'index' => 'knowledge_chunks',
                'body' => [
                    'query' => [
                        'term' => ['knowledge_base_id' => $knowledgeBaseId],
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            // Игнорируем ошибки если индекс пуст
        }
    }

    /**
     * Индексирует чанки в Elasticsearch
     */
    protected function indexToElasticsearch(int $knowledgeBaseId): void
    {
        $chunks = KnowledgeChunk::where('knowledge_base_id', $knowledgeBaseId)
            ->where('is_active', true)
            ->get();

        $bar = $this->output->createProgressBar($chunks->count());
        $bar->start();

        $body = [];

        foreach ($chunks as $chunk) {
            $body[] = [
                'index' => [
                    '_index' => 'knowledge_chunks',
                    '_id' => $chunk->id,
                ],
            ];
            
            $body[] = [
                'id' => $chunk->id,
                'knowledge_base_id' => $chunk->knowledge_base_id,
                'title' => $chunk->title,
                'content' => $chunk->content,
                'source_url' => $chunk->source_url,
                'chunk_index' => $chunk->chunk_index,
                'is_active' => $chunk->is_active,
                'created_at' => $chunk->created_at?->toIso8601String(),
            ];

            // Отправляем пакетами по 100
            if (count($body) >= 200) {
                $this->elasticsearch->bulk(['body' => $body]);
                $body = [];
            }

            $bar->advance();
        }

        // Отправляем остаток
        if (!empty($body)) {
            $this->elasticsearch->bulk(['body' => $body]);
        }

        $bar->finish();
    }

    /**
     * Разбивает документ на чанки
     */
    protected function splitIntoChunks(KnowledgeItem $item, int $maxSize): array
    {
        $content = $item->content;
        $title = $item->title;

        // Если документ маленький — возвращаем как есть
        if (strlen($content) <= $maxSize) {
            return [
                [
                    'title' => $title,
                    'content' => $content,
                ]
            ];
        }

        $chunks = [];
        
        // Пробуем разбить по секциям
        $sections = $this->splitBySections($content);

        if (count($sections) > 1) {
            foreach ($sections as $section) {
                if (strlen($section['content']) <= $maxSize) {
                    $chunks[] = $section;
                } else {
                    $subChunks = $this->splitByParagraphs($section['content'], $section['title'], $maxSize);
                    $chunks = array_merge($chunks, $subChunks);
                }
            }
        } else {
            $chunks = $this->splitByParagraphs($content, $title, $maxSize);
        }

        return $chunks;
    }

    /**
     * Разбивает по секциям (заголовкам)
     */
    protected function splitBySections(string $content): array
    {
        $sections = [];
        
        $patterns = [
            '/^(#{1,3})\s+(.+)$/m',
            '/^(💡|📌|⚠️|✅|❌|🔹|▶️|📋|🎯|📝|💰|🎁|👉)\s*(.+)$/mu',
            '/^([А-ЯЁA-Z][А-ЯЁA-Z\s]{5,50}[:.?]?)$/m',
            '/^(\d+[\.\)]\s+.{10,80})$/m',
        ];

        $allMatches = [];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $allMatches[$match[1]] = trim($match[0]);
                }
            }
        }

        if (empty($allMatches)) {
            return [['title' => '', 'content' => $content]];
        }

        ksort($allMatches);
        
        $positions = array_keys($allMatches);
        $titles = array_values($allMatches);

        for ($i = 0; $i < count($positions); $i++) {
            $start = $positions[$i];
            $end = isset($positions[$i + 1]) ? $positions[$i + 1] : strlen($content);
            
            $sectionContent = trim(substr($content, $start, $end - $start));
            
            if (strlen($sectionContent) > 50) {
                $sections[] = [
                    'title' => Str::limit($titles[$i], 100),
                    'content' => $sectionContent,
                ];
            }
        }

        if ($positions[0] > 100) {
            $preContent = trim(substr($content, 0, $positions[0]));
            array_unshift($sections, [
                'title' => Str::limit($preContent, 80),
                'content' => $preContent,
            ]);
        }

        return $sections ?: [['title' => '', 'content' => $content]];
    }

    /**
     * Разбивает по параграфам
     */
    protected function splitByParagraphs(string $content, string $baseTitle, int $maxSize): array
    {
        $chunks = [];
        $paragraphs = preg_split('/\n\s*\n+/', $content);
        
        $currentChunk = '';
        $chunkNum = 1;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            
            if (empty($paragraph)) {
                continue;
            }

            $newLength = strlen($currentChunk) + strlen($paragraph) + 2;

            if ($newLength > $maxSize && !empty($currentChunk)) {
                $chunks[] = [
                    'title' => $baseTitle . ' (часть ' . $chunkNum . ')',
                    'content' => trim($currentChunk),
                ];
                $currentChunk = $paragraph;
                $chunkNum++;
            } else {
                $currentChunk .= "\n\n" . $paragraph;
            }
        }

        if (!empty(trim($currentChunk))) {
            $chunks[] = [
                'title' => count($chunks) > 0 ? $baseTitle . ' (часть ' . $chunkNum . ')' : $baseTitle,
                'content' => trim($currentChunk),
            ];
        }

        return $chunks;
    }
}