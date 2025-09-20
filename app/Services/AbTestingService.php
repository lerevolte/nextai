<?php

namespace App\Services;

use App\Models\AbTest;
use App\Models\AbTestVariant;
use App\Models\Bot;
use App\Models\Conversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AbTestingService
{
    /**
     * Создать новый A/B тест
     */
    public function createTest(array $data): AbTest
    {
        DB::beginTransaction();
        try {
            $test = AbTest::create([
                'organization_id' => $data['organization_id'],
                'bot_id' => $data['bot_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'], // prompt, temperature, model, etc.
                'status' => 'draft',
                'traffic_percentage' => $data['traffic_percentage'] ?? 100,
                'min_sample_size' => $data['min_sample_size'] ?? 100,
                'confidence_level' => $data['confidence_level'] ?? 95,
                'starts_at' => $data['starts_at'] ?? now(),
                'ends_at' => $data['ends_at'] ?? null,
                'settings' => $data['settings'] ?? []
            ]);

            // Создаем варианты теста
            foreach ($data['variants'] as $index => $variant) {
                $test->variants()->create([
                    'name' => $variant['name'],
                    'description' => $variant['description'] ?? null,
                    'config' => $variant['config'], // Конфигурация варианта (промпт, температура и т.д.)
                    'traffic_allocation' => $variant['traffic_allocation'] ?? (100 / count($data['variants'])),
                    'is_control' => $index === 0, // Первый вариант - контрольный
                ]);
            }

            DB::commit();
            return $test->load('variants');
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to create A/B test', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Выбрать вариант для нового диалога
     */
    public function selectVariant(Bot $bot, ?string $userId = null): ?AbTestVariant
    {
        // Получаем активные тесты для бота
        $activeTests = $bot->abTests()
            ->active()
            ->with('variants')
            ->get();

        if ($activeTests->isEmpty()) {
            return null;
        }

        // Выбираем тест (если несколько активных, берем с наивысшим приоритетом)
        $test = $activeTests->sortByDesc('priority')->first();

        // Проверяем, попадает ли пользователь в тест (traffic_percentage)
        if (rand(1, 100) > $test->traffic_percentage) {
            return null;
        }

        // Выбираем вариант на основе распределения трафика
        return $this->allocateVariant($test, $userId);
    }

    /**
     * Распределить пользователя по варианту
     */
    protected function allocateVariant(AbTest $test, ?string $userId = null): AbTestVariant
    {
        // Если есть userId, используем детерминированное распределение
        if ($userId) {
            $hash = crc32($userId . $test->id);
            $bucket = $hash % 100;
        } else {
            $bucket = rand(1, 100);
        }

        $cumulative = 0;
        foreach ($test->variants as $variant) {
            $cumulative += $variant->traffic_allocation;
            if ($bucket <= $cumulative) {
                return $variant;
            }
        }

        // На всякий случай возвращаем последний вариант
        return $test->variants->last();
    }

    /**
     * Записать результат использования варианта
     */
    public function recordResult(Conversation $conversation, AbTestVariant $variant, array $metrics = []): void
    {
        // Создаем запись результата
        DB::table('ab_test_results')->insert([
            'ab_test_id' => $variant->ab_test_id,
            'variant_id' => $variant->id,
            'conversation_id' => $conversation->id,
            'metrics' => json_encode($metrics),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Обновляем статистику варианта
        $this->updateVariantStats($variant);
    }

    /**
     * Обновить статистику варианта
     */
    protected function updateVariantStats(AbTestVariant $variant): void
    {
        $stats = DB::table('ab_test_results')
            ->where('variant_id', $variant->id)
            ->join('conversations', 'ab_test_results.conversation_id', '=', 'conversations.id')
            ->select(
                DB::raw('COUNT(*) as total_conversations'),
                DB::raw('AVG(conversations.messages_count) as avg_messages'),
                DB::raw('AVG(JSON_EXTRACT(ab_test_results.metrics, "$.response_time")) as avg_response_time'),
                DB::raw('SUM(CASE WHEN conversations.status = "closed" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN conversations.status = "waiting_operator" THEN 1 ELSE 0 END) as transferred'),
                DB::raw('AVG(JSON_EXTRACT(ab_test_results.metrics, "$.satisfaction_score")) as avg_satisfaction')
            )
            ->first();

        $variant->update([
            'conversions' => $stats->completed ?? 0,
            'participants' => $stats->total_conversations ?? 0,
            'conversion_rate' => $stats->total_conversations > 0 
                ? ($stats->completed / $stats->total_conversations) * 100 
                : 0,
            'metrics' => [
                'avg_messages' => round($stats->avg_messages ?? 0, 2),
                'avg_response_time' => round($stats->avg_response_time ?? 0, 2),
                'transfer_rate' => $stats->total_conversations > 0 
                    ? round(($stats->transferred / $stats->total_conversations) * 100, 2)
                    : 0,
                'avg_satisfaction' => round($stats->avg_satisfaction ?? 0, 2)
            ]
        ]);
    }

    /**
     * Анализ результатов A/B теста
     */
    public function analyzeTest(AbTest $test): array
    {
        $variants = $test->variants()->with(['results'])->get();
        $control = $variants->where('is_control', true)->first();
        
        if (!$control) {
            return ['error' => 'No control variant found'];
        }

        $analysis = [
            'test_id' => $test->id,
            'test_name' => $test->name,
            'status' => $test->status,
            'total_participants' => $variants->sum('participants'),
            'duration_days' => $test->starts_at->diffInDays(now()),
            'variants' => [],
            'winner' => null,
            'confidence' => null,
            'recommendations' => []
        ];

        foreach ($variants as $variant) {
            $variantAnalysis = [
                'id' => $variant->id,
                'name' => $variant->name,
                'is_control' => $variant->is_control,
                'participants' => $variant->participants,
                'conversions' => $variant->conversions,
                'conversion_rate' => $variant->conversion_rate,
                'metrics' => $variant->metrics,
                'statistical_significance' => null,
                'improvement' => null
            ];

            // Рассчитываем статистическую значимость для не-контрольных вариантов
            if (!$variant->is_control && $control->participants > 0 && $variant->participants > 0) {
                $significance = $this->calculateStatisticalSignificance(
                    $control->conversions,
                    $control->participants,
                    $variant->conversions,
                    $variant->participants
                );

                $variantAnalysis['statistical_significance'] = $significance;
                $variantAnalysis['improvement'] = $control->conversion_rate > 0
                    ? round((($variant->conversion_rate - $control->conversion_rate) / $control->conversion_rate) * 100, 2)
                    : 0;

                // Определяем победителя
                if ($significance >= $test->confidence_level && $variantAnalysis['improvement'] > 0) {
                    if (!$analysis['winner'] || $variantAnalysis['improvement'] > $analysis['winner']['improvement']) {
                        $analysis['winner'] = $variantAnalysis;
                    }
                }
            }

            $analysis['variants'][] = $variantAnalysis;
        }

        // Генерируем рекомендации
        $analysis['recommendations'] = $this->generateRecommendations($analysis, $test);

        return $analysis;
    }

    /**
     * Расчет статистической значимости (Z-test)
     */
    protected function calculateStatisticalSignificance($controlConversions, $controlTotal, $variantConversions, $variantTotal): float
    {
        if ($controlTotal == 0 || $variantTotal == 0) {
            return 0;
        }

        $controlRate = $controlConversions / $controlTotal;
        $variantRate = $variantConversions / $variantTotal;

        $pooledRate = ($controlConversions + $variantConversions) / ($controlTotal + $variantTotal);
        $pooledStdError = sqrt($pooledRate * (1 - $pooledRate) * (1/$controlTotal + 1/$variantTotal));

        if ($pooledStdError == 0) {
            return 0;
        }

        $zScore = abs(($variantRate - $controlRate) / $pooledStdError);
        
        // Преобразуем Z-score в уровень доверия (упрощенная формула)
        if ($zScore >= 2.58) return 99; // 99% confidence
        if ($zScore >= 1.96) return 95; // 95% confidence
        if ($zScore >= 1.64) return 90; // 90% confidence
        
        return round($zScore * 30, 2); // Приблизительное значение для меньших z-scores
    }

    /**
     * Генерация рекомендаций
     */
    protected function generateRecommendations(array $analysis, AbTest $test): array
    {
        $recommendations = [];

        // Проверяем размер выборки
        $totalParticipants = $analysis['total_participants'];
        if ($totalParticipants < $test->min_sample_size) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => "Недостаточный размер выборки. Необходимо еще " . 
                    ($test->min_sample_size - $totalParticipants) . " участников для достоверных результатов"
            ];
        }

        // Проверяем наличие победителя
        if ($analysis['winner']) {
            $recommendations[] = [
                'type' => 'success',
                'message' => "Вариант '" . $analysis['winner']['name'] . "' показывает улучшение на " . 
                    $analysis['winner']['improvement'] . "% с уровнем доверия " . 
                    $analysis['winner']['statistical_significance'] . "%"
            ];
            
            if ($analysis['winner']['statistical_significance'] >= $test->confidence_level) {
                $recommendations[] = [
                    'type' => 'action',
                    'message' => "Рекомендуется завершить тест и применить победивший вариант"
                ];
            }
        } else {
            $recommendations[] = [
                'type' => 'info',
                'message' => "Пока нет статистически значимого победителя. Продолжайте тестирование"
            ];
        }

        // Проверяем длительность теста
        if ($analysis['duration_days'] > 30) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => "Тест идет более 30 дней. Рассмотрите возможность его завершения или изменения параметров"
            ];
        }

        return $recommendations;
    }

    /**
     * Автоматическое завершение теста при достижении условий
     */
    public function checkAndCompleteTests(): void
    {
        $tests = AbTest::active()
            ->where(function ($query) {
                $query->whereNotNull('ends_at')
                    ->where('ends_at', '<=', now())
                    ->orWhereRaw('(SELECT SUM(participants) FROM ab_test_variants WHERE ab_test_id = ab_tests.id) >= min_sample_size * 2');
            })
            ->get();

        foreach ($tests as $test) {
            $analysis = $this->analyzeTest($test);
            
            // Если есть статистически значимый победитель
            if ($analysis['winner'] && $analysis['winner']['statistical_significance'] >= $test->confidence_level) {
                $this->completeTest($test, $analysis['winner']['id']);
            }
            // Если тест истек по времени
            elseif ($test->ends_at && $test->ends_at->isPast()) {
                $this->completeTest($test, null);
            }
        }
    }

    /**
     * Завершить тест
     */
    public function completeTest(AbTest $test, ?int $winnerVariantId = null): void
    {
        DB::transaction(function () use ($test, $winnerVariantId) {
            $test->update([
                'status' => 'completed',
                'completed_at' => now(),
                'winner_variant_id' => $winnerVariantId
            ]);

            // Если есть победитель, можем автоматически применить его настройки
            if ($winnerVariantId) {
                $winner = AbTestVariant::find($winnerVariantId);
                if ($winner && $test->auto_apply_winner) {
                    $this->applyWinnerSettings($test->bot, $winner);
                }
            }

            // Отправляем уведомление о завершении теста
            $this->notifyTestCompletion($test, $winnerVariantId);
        });
    }

    /**
     * Применить настройки победившего варианта
     */
    protected function applyWinnerSettings(Bot $bot, AbTestVariant $winner): void
    {
        $config = $winner->config;
        
        switch ($winner->abTest->type) {
            case 'prompt':
                $bot->update(['system_prompt' => $config['prompt']]);
                break;
            case 'temperature':
                $bot->update(['temperature' => $config['temperature']]);
                break;
            case 'model':
                $bot->update(['ai_model' => $config['model']]);
                break;
            case 'welcome_message':
                $bot->update(['welcome_message' => $config['welcome_message']]);
                break;
        }
        
        Log::info('Applied A/B test winner settings', [
            'bot_id' => $bot->id,
            'test_id' => $winner->ab_test_id,
            'variant_id' => $winner->id
        ]);
    }

    /**
     * Уведомить о завершении теста
     */
    protected function notifyTestCompletion(AbTest $test, ?int $winnerVariantId): void
    {
        // Здесь можно отправить email, уведомление в Slack и т.д.
        Log::info('A/B test completed', [
            'test_id' => $test->id,
            'winner_variant_id' => $winnerVariantId
        ]);
    }
}

