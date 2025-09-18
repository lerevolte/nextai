<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    /**
     * Генерирует эмбеддинг для текста
     */
    public function generateEmbedding(string $text): ?array
    {
        try {
            // Ограничиваем длину текста (OpenAI имеет лимит)
            $text = mb_substr($text, 0, 8000);
            
            // Используем OpenAI для генерации эмбеддингов
            $response = OpenAI::embeddings()->create([
                'model' => 'text-embedding-ada-002',
                'input' => $text,
            ]);

            return $response->embeddings[0]->embedding;
            
        } catch (\Exception $e) {
            Log::error('Failed to generate embedding: ' . $e->getMessage());
            
            // Fallback: генерируем простой эмбеддинг на основе TF-IDF
            return $this->generateSimpleEmbedding($text);
        }
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
        $embedding = array_fill(0, 384, 0); // Используем 384 размерность для совместимости
        
        foreach ($wordCount as $word => $count) {
            // Простой хэш для определения позиции в векторе
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

            $similarity = $this->cosineSimilarity($queryEmbedding, $itemEmbedding);
            $similarities[] = [
                'item' => $item,
                'similarity' => $similarity,
            ];
        }

        // Сортируем по убыванию схожести
        usort($similarities, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // Возвращаем топ N результатов
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

        // Добавляем векторные результаты с весом
        foreach ($vectorResults as $result) {
            $id = $result['item']->id;
            if (!isset($seen[$id])) {
                $combined[] = [
                    'item' => $result['item'],
                    'score' => $result['similarity'] * 0.7, // Вес векторного поиска
                ];
                $seen[$id] = true;
            }
        }

        // Добавляем результаты полнотекстового поиска
        foreach ($textResults as $item) {
            if (!isset($seen[$item->id])) {
                $combined[] = [
                    'item' => $item,
                    'score' => 0.3, // Базовый вес текстового поиска
                ];
                $seen[$item->id] = true;
            } else {
                // Увеличиваем score если найдено обоими методами
                foreach ($combined as &$c) {
                    if ($c['item']->id === $item->id) {
                        $c['score'] += 0.3;
                        break;
                    }
                }
            }
        }

        // Сортируем по общему score
        usort($combined, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Возвращаем топ N
        return array_slice($combined, 0, $limit);
    }
}