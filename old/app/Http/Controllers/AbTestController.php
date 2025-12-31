<?php

namespace App\Http\Controllers;

use App\Models\AbTest;
use App\Models\Bot;
use App\Models\Organization;
use App\Services\AbTestingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbTestController extends Controller
{
    protected AbTestingService $abTestingService;

    public function __construct(AbTestingService $abTestingService)
    {
        $this->abTestingService = $abTestingService;
    }

    /**
     * Список A/B тестов
     */
    public function index(Organization $organization)
    {
        $tests = AbTest::where('organization_id', $organization->id)
            ->with(['bot', 'variants'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('ab-tests.index', compact('organization', 'tests'));
    }

    /**
     * Форма создания теста
     */
    public function create(Organization $organization)
    {
        $bots = $organization->bots()->active()->get();
        
        return view('ab-tests.create', compact('organization', 'bots'));
    }

    /**
     * Сохранение нового теста
     */
    public function store(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'bot_id' => 'required|exists:bots,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:prompt,temperature,model,welcome_message',
            'traffic_percentage' => 'required|integer|min:1|max:100',
            'min_sample_size' => 'required|integer|min:10',
            'confidence_level' => 'required|integer|min:80|max:99',
            'starts_at' => 'nullable|date|after_or_equal:today',
            'ends_at' => 'nullable|date|after:starts_at',
            'auto_apply_winner' => 'boolean',
            'variants' => 'required|array|min:2|max:5',
            'variants.*.name' => 'required|string|max:255',
            'variants.*.description' => 'nullable|string',
            'variants.*.traffic_allocation' => 'required|numeric|min:0|max:100',
            'variants.*.config' => 'required|array',
        ]);

        // Проверяем, что сумма traffic_allocation = 100
        $totalAllocation = collect($validated['variants'])->sum('traffic_allocation');
        if (abs($totalAllocation - 100) > 0.01) {
            return back()->withErrors(['variants' => 'Сумма распределения трафика должна быть 100%']);
        }

        // Проверяем, что бот принадлежит организации
        $bot = Bot::where('id', $validated['bot_id'])
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        try {
            $testData = array_merge($validated, [
                'organization_id' => $organization->id,
            ]);

            $test = $this->abTestingService->createTest($testData);

            return redirect()
                ->route('ab-tests.show', [$organization, $test])
                ->with('success', 'A/B тест успешно создан');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Ошибка при создании теста: ' . $e->getMessage()]);
        }
    }

    /**
     * Просмотр теста
     */
    public function show(Organization $organization, AbTest $test)
    {
        // Проверяем доступ
        if ($test->organization_id !== $organization->id) {
            abort(403);
        }

        $test->load(['bot', 'variants.results']);
        
        // Получаем анализ теста
        $analysis = $this->abTestingService->analyzeTest($test);

        return view('ab-tests.show', compact('organization', 'test', 'analysis'));
    }

    /**
     * Детальный анализ теста
     */
    public function analysis(Organization $organization, AbTest $test)
    {
        // Проверяем доступ
        if ($test->organization_id !== $organization->id) {
            abort(403);
        }

        $analysis = $this->abTestingService->analyzeTest($test);
        
        // Получаем дополнительную статистику
        $dailyStats = $this->getDailyStats($test);
        $conversionFunnel = $this->getConversionFunnel($test);
        
        return view('ab-tests.analysis', compact('organization', 'test', 'analysis', 'dailyStats', 'conversionFunnel'));
    }

    /**
     * Завершение теста
     */
    public function complete(Request $request, Organization $organization, AbTest $test)
    {
        // Проверяем доступ
        if ($test->organization_id !== $organization->id) {
            abort(403);
        }

        if ($test->status !== 'active') {
            return back()->withErrors(['error' => 'Тест не активен']);
        }

        $request->validate([
            'winner_variant_id' => 'nullable|exists:ab_test_variants,id',
            'apply_winner' => 'boolean'
        ]);

        try {
            $this->abTestingService->completeTest($test, $request->winner_variant_id);

            if ($request->apply_winner && $request->winner_variant_id) {
                // Применяем настройки победителя
                $winner = $test->variants()->find($request->winner_variant_id);
                if ($winner) {
                    $this->abTestingService->applyWinnerSettings($test->bot, $winner);
                }
            }

            return redirect()
                ->route('ab-tests.show', [$organization, $test])
                ->with('success', 'Тест успешно завершен');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Ошибка при завершении теста: ' . $e->getMessage()]);
        }
    }

    /**
     * Приостановка теста
     */
    public function pause(Organization $organization, AbTest $test)
    {
        // Проверяем доступ
        if ($test->organization_id !== $organization->id) {
            abort(403);
        }

        if ($test->status !== 'active') {
            return back()->withErrors(['error' => 'Тест не активен']);
        }

        $test->update(['status' => 'paused']);

        return redirect()
            ->route('ab-tests.show', [$organization, $test])
            ->with('success', 'Тест приостановлен');
    }

    /**
     * Удаление теста
     */
    public function destroy(Organization $organization, AbTest $test)
    {
        // Проверяем доступ
        if ($test->organization_id !== $organization->id) {
            abort(403);
        }

        if ($test->status === 'active') {
            return back()->withErrors(['error' => 'Нельзя удалить активный тест']);
        }

        $test->delete();

        return redirect()
            ->route('ab-tests.index', $organization)
            ->with('success', 'Тест удален');
    }

    /**
     * Получение ежедневной статистики
     */
    protected function getDailyStats(AbTest $test)
    {
        return DB::table('ab_test_results')
            ->where('ab_test_id', $test->id)
            ->select(
                DB::raw('DATE(created_at) as date'),
                'variant_id',
                DB::raw('COUNT(*) as participants'),
                DB::raw('SUM(JSON_EXTRACT(metrics, "$.converted")) as conversions')
            )
            ->groupBy('date', 'variant_id')
            ->orderBy('date')
            ->get();
    }

    /**
     * Получение воронки конверсии
     */
    protected function getConversionFunnel(AbTest $test)
    {
        $funnel = [];
        
        foreach ($test->variants as $variant) {
            $funnel[$variant->id] = [
                'name' => $variant->name,
                'started' => $variant->participants,
                'engaged' => DB::table('ab_test_results')
                    ->where('variant_id', $variant->id)
                    ->whereRaw('JSON_EXTRACT(metrics, "$.messages_count") > 1')
                    ->count(),
                'converted' => $variant->conversions,
            ];
        }
        
        return $funnel;
    }
}