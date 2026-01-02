<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    protected ?string $proxyUrl;
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->proxyUrl = config('chatbot.proxy_url');
        $this->apiKey = config('openai.api_key', '');
        $this->baseUrl = config('openai.base_url', 'https://api.openai.com/v1');
    }

    /**
     * Генерирует эмбеддинг для текста
     */
    public function generateEmbedding(string $text): ?array
    {
        try {
            // Ограничиваем длину текста (OpenAI имеет лимит ~8191 токенов)
            $text = mb_substr($text, 0, 8000);
            
            // Используем HTTP клиент с прокси
            $response = $this->makeOpenAIRequest('embeddings', [
                'model' => 'text-embedding-ada-002',
                'input' => $text,
            ]);

            if (isset($response['data'][0]['embedding'])) {
                return $response['data'][0]['embedding'];
            }

            Log::error('Invalid embedding response structure', ['response' => $response]);
            return $this->generateSimpleEmbedding($text);
            
        } catch (\Exception $e) {
            Log::error('Failed to generate embedding: ' . $e->getMessage());
            
            // Fallback: генерируем простой эмбеддинг на основе TF-IDF
            return $this->generateSimpleEmbedding($text);
        }
    }

    /**
     * Выполняет запрос к OpenAI API с поддержкой прокси
     */
    protected function makeOpenAIRequest(string $endpoint, array $data): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . $endpoint;

        $httpClient = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60);

        // Добавляем прокси если настроен
        if (!empty($this->proxyUrl)) {
            $httpClient = $httpClient->withOptions([
                'proxy' => $this->proxyUrl,
                'verify' => false,
            ]);
        }

        $response = $httpClient->post($url, $data);

        if (!$response->successful()) {
            $error = $response->json('error.message', $response->body());
            throw new \Exception("OpenAI API error: {$error}");
        }

        return $response->json();
    }

    /**
     * Простой эмбеддинг на основе частоты слов (fallback)
     */
    protected function generateSimpleEmbedding(string $text): array
    {
        // Токенизация
        $words = str_word_count(mb_strtolower($text), 1, 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя');
        $wordCount = array_count_values($words);
        
        // Создаем вектор фиксированной длины
        $embedding = array_fill(0, 384, 0);
        
        foreach ($wordCount as $word => $count) {
            $position = abs(crc32($word)) % 384;
            $embedding[$position] += $count / count($words);
        }
        
        // Нормализация вектора
        $magnitude = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $embedding)));
        if ($magnitude > 0) {
            $embedding = array_map(function($x) use ($magnitude) { 
                return $x / $magnitude; 
            }, $embedding);
        }
        
        return $embedding;
    }

    /**
     * Вычисляет косинусное сходство между двумя векторами
     */
    public function cosineSimilarity(array $vec1, array $vec2): float
    {
        if (count($vec1) !== count($vec2)) {
            return 0;
        }

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $magnitude1 += $vec1[$i] * $vec1[$i];
            $magnitude2 += $vec2[$i] * $vec2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Поиск похожих документов по эмбеддингу
     */
    public function findSimilar(array $queryEmbedding, $knowledgeBase, int $limit = 3): array
    {
        $items = $knowledgeBase->items()
            ->where('is_active', true)
            ->whereNotNull('embedding')
            ->get();

        $similarities = [];

        foreach ($items as $item) {
            $itemEmbedding = json_decode($item->embedding, true);
            
            if (!$itemEmbedding || !is_array($itemEmbedding)) {
                continue;
            }

            // Проверяем совместимость размерностей
            if (count($queryEmbedding) !== count($itemEmbedding)) {
                Log::warning("Embedding dimension mismatch", [
                    'query' => count($queryEmbedding),
                    'item' => count($itemEmbedding),
                    'item_id' => $item->id,
                ]);
                continue;
            }

            $similarity = $this->cosineSimilarity($queryEmbedding, $itemEmbedding);
            $similarities[] = [
                'item' => $item,
                'similarity' => $similarity,
            ];
        }

        usort($similarities, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($similarities, 0, $limit);
    }

    /**
     * Гибридный поиск (векторный + полнотекстовый)
     */
    public function hybridSearch(string $query, $knowledgeBase, int $limit = 3): array
    {
        // 1. Векторный поиск
        $queryEmbedding = $this->generateEmbedding($query);
        $vectorResults = [];
        
        if ($queryEmbedding) {
            $vectorResults = $this->findSimilar($queryEmbedding, $knowledgeBase, $limit * 2);
        }

        // 2. Полнотекстовый поиск
        $textResults = $knowledgeBase->items()
            ->where('is_active', true)
            ->where(function($q) use ($query) {
                $q->where('title', 'LIKE', '%' . $query . '%')
                  ->orWhere('content', 'LIKE', '%' . $query . '%');
            })
            ->limit($limit * 2)
            ->get();

        // 3. Объединяем результаты
        $combined = [];
        $seen = [];

        foreach ($vectorResults as $result) {
            $id = $result['item']->id;
            if (!isset($seen[$id])) {
                $combined[] = [
                    'item' => $result['item'],
                    'score' => $result['similarity'] * 0.7,
                ];
                $seen[$id] = true;
            }
        }

        foreach ($textResults as $item) {
            if (!isset($seen[$item->id])) {
                $combined[] = [
                    'item' => $item,
                    'score' => 0.3,
                ];
                $seen[$item->id] = true;
            } else {
                foreach ($combined as &$c) {
                    if ($c['item']->id === $item->id) {
                        $c['score'] += 0.3;
                        break;
                    }
                }
            }
        }

        usort($combined, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($combined, 0, $limit);
    }

    /**
     * Перегенерация эмбеддингов для всех элементов базы знаний
     */
    public function regenerateEmbeddings($knowledgeBase): array
    {
        $stats = ['success' => 0, 'failed' => 0, 'skipped' => 0];
        
        $items = $knowledgeBase->items()->where('is_active', true)->get();
        
        foreach ($items as $item) {
            try {
                $text = $item->title . "\n\n" . $item->content;
                $embedding = $this->generateEmbedding($text);
                
                if ($embedding && count($embedding) === 1536) {
                    $item->update(['embedding' => json_encode($embedding)]);
                    $stats['success']++;
                } else {
                    $stats['failed']++;
                    Log::warning("Invalid embedding generated for item {$item->id}");
                }
            } catch (\Exception $e) {
                $stats['failed']++;
                Log::error("Failed to regenerate embedding for item {$item->id}: " . $e->getMessage());
            }
        }
        
        return $stats;
    }
}