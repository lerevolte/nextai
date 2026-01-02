<?php

namespace App\Services;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeChunk;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Log;

class KnowledgeSearchService
{
    protected Client $elasticsearch;
    protected string $indexName = 'knowledge_chunks';

    public function __construct(Client $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Поиск релевантных чанков для вопроса
     */
    public function search(string $query, KnowledgeBase $knowledgeBase, int $limit = 5): array
    {
        try {
            $result = $this->elasticsearch->search([
                'index' => $this->indexName,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                'multi_match' => [
                                    'query' => $query,
                                    'fields' => ['title^3', 'content'],
                                    'type' => 'best_fields',
                                    'fuzziness' => 'AUTO',
                                ],
                            ],
                            'filter' => [
                                ['term' => ['knowledge_base_id' => $knowledgeBase->id]],
                                ['term' => ['is_active' => true]],
                            ],
                        ],
                    ],
                    'size' => $limit,
                    '_source' => ['id', 'title', 'content', 'source_url', 'chunk_index'],
                ],
            ]);

            return $this->formatResults($result->asArray());

        } catch (\Exception $e) {
            Log::error('Elasticsearch search failed: ' . $e->getMessage());
            
            // Fallback на поиск по базе данных
            return $this->fallbackSearch($query, $knowledgeBase, $limit);
        }
    }

    /**
     * Поиск с минимальным порогом релевантности
     */
    public function searchWithThreshold(
        string $query, 
        KnowledgeBase $knowledgeBase, 
        float $minScore = 1.0,
        int $limit = 5
    ): array {
        $results = $this->search($query, $knowledgeBase, $limit);
        
        return array_filter($results, function ($item) use ($minScore) {
            return $item['score'] >= $minScore;
        });
    }

    /**
     * Форматирует контекст для передачи в AI
     */
    public function getContextForAI(string $query, KnowledgeBase $knowledgeBase, int $maxChunks = 3): string
    {
        $results = $this->searchWithThreshold($query, $knowledgeBase, 1.0, $maxChunks);

        if (empty($results)) {
            return '';
        }

        $context = "Релевантная информация из базы знаний:\n\n";

        foreach ($results as $index => $result) {
            $context .= "--- Источник " . ($index + 1) . " ---\n";
            $context .= "Заголовок: " . $result['title'] . "\n";
            $context .= "Содержание:\n" . $result['content'] . "\n\n";
        }

        return $context;
    }

    /**
     * Форматирует результаты поиска
     */
    protected function formatResults(array $elasticResponse): array
    {
        $results = [];

        foreach ($elasticResponse['hits']['hits'] as $hit) {
            $results[] = [
                'id' => $hit['_source']['id'],
                'title' => $hit['_source']['title'],
                'content' => $hit['_source']['content'],
                'source_url' => $hit['_source']['source_url'] ?? null,
                'chunk_index' => $hit['_source']['chunk_index'] ?? 0,
                'score' => $hit['_score'],
            ];
        }

        return $results;
    }

    /**
     * Fallback поиск по базе данных
     */
    protected function fallbackSearch(string $query, KnowledgeBase $knowledgeBase, int $limit): array
    {
        $chunks = KnowledgeChunk::where('knowledge_base_id', $knowledgeBase->id)
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('content', 'LIKE', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        return $chunks->map(function ($chunk) {
            return [
                'id' => $chunk->id,
                'title' => $chunk->title,
                'content' => $chunk->content,
                'source_url' => $chunk->source_url,
                'chunk_index' => $chunk->chunk_index,
                'score' => 1.0,
            ];
        })->toArray();
    }

    /**
     * Проверяет доступность Elasticsearch
     */
    public function isAvailable(): bool
    {
        try {
            $this->elasticsearch->ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Получает статистику индекса
     */
    public function getIndexStats(): array
    {
        try {
            $stats = $this->elasticsearch->indices()->stats(['index' => $this->indexName]);
            $count = $this->elasticsearch->count(['index' => $this->indexName]);

            return [
                'documents' => $count['count'],
                'size' => $stats['indices'][$this->indexName]['total']['store']['size_in_bytes'] ?? 0,
                'available' => true,
            ];
        } catch (\Exception $e) {
            return [
                'documents' => 0,
                'size' => 0,
                'available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}