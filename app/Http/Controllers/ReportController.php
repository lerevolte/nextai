<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\GeneratedReport;
use App\Models\ScheduledReport;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    protected ReportExportService $reportService;

    public function __construct(ReportExportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Список отчетов
     */
    public function index(Organization $organization)
    {
        $generatedReports = GeneratedReport::where('organization_id', $organization->id)
            ->orderBy('generated_at', 'desc')
            ->limit(20)
            ->get();

        $scheduledReports = ScheduledReport::where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get();

        return view('reports.index', compact('organization', 'generatedReports', 'scheduledReports'));
    }

    /**
     * Генерация отчета
     */
    public function generate(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'format' => 'required|in:pdf,excel,csv,json',
            'period' => 'required|integer|min:1|max:365',
            'bot_id' => 'nullable|exists:bots,id',
            'include_charts' => 'boolean',
            'email' => 'nullable|email',
        ]);

        // Проверяем, что бот принадлежит организации
        if ($validated['bot_id'] ?? null) {
            $bot = $organization->bots()->find($validated['bot_id']);
            if (!$bot) {
                return back()->withErrors(['bot_id' => 'Бот не найден']);
            }
        }

        try {
            $reportUrl = $this->reportService->generateReport($organization, $validated);

            // Сохраняем информацию о сгенерированном отчете
            $report = GeneratedReport::create([
                'organization_id' => $organization->id,
                'name' => 'Отчет за ' . $validated['period'] . ' дней',
                'format' => $validated['format'],
                'file_path' => $reportUrl,
                'file_size' => Storage::size($reportUrl),
                'parameters' => $validated,
                'metrics_snapshot' => [], // Здесь можно сохранить снимок метрик
                'generated_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            // Отправляем на email если указан
            if ($validated['email'] ?? null) {
                // Здесь можно добавить отправку email
                \Mail::to($validated['email'])->send(new \App\Mail\ReportGenerated($reportUrl, $organization));
            }

            return redirect()
                ->route('reports.download', [$organization, $report])
                ->with('success', 'Отчет успешно сгенерирован');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Ошибка генерации отчета: ' . $e->getMessage()]);
        }
    }

    /**
     * Список запланированных отчетов
     */
    public function scheduled(Organization $organization)
    {
        $reports = ScheduledReport::where('organization_id', $organization->id)
            ->with('generatedReports')
            ->paginate(10);

        return view('reports.scheduled', compact('organization', 'reports'));
    }

    /**
     * Планирование отчета
     */
    public function schedule(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly',
            'format' => 'required|in:pdf,excel,csv,json',
            'recipients' => 'required|array',
            'recipients.*' => 'email',
            'config' => 'nullable|array',
            'config.include_charts' => 'boolean',
            'config.period' => 'integer|min:1|max:365',
            'config.bot_id' => 'nullable|exists:bots,id',
        ]);

        try {
            $this->reportService->scheduleReport($organization, $validated);

            return redirect()
                ->route('reports.scheduled', $organization)
                ->with('success', 'Отчет запланирован');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Ошибка планирования: ' . $e->getMessage()]);
        }
    }

    /**
     * Скачивание отчета
     */
    public function download(Organization $organization, GeneratedReport $report)
    {
        // Проверяем доступ
        if ($report->organization_id !== $organization->id) {
            abort(403);
        }

        // Проверяем срок действия
        if ($report->isExpired()) {
            return back()->withErrors(['error' => 'Срок действия отчета истек']);
        }

        $filePath = $report->file_path;

        if (!Storage::exists($filePath)) {
            return back()->withErrors(['error' => 'Файл отчета не найден']);
        }

        return Storage::download($filePath, 'report_' . $report->id . '.' . $report->format);
    }

    /**
     * Удаление запланированного отчета
     */
    public function deleteScheduled(Organization $organization, ScheduledReport $report)
    {
        // Проверяем доступ
        if ($report->organization_id !== $organization->id) {
            abort(403);
        }

        $report->update(['is_active' => false]);

        return redirect()
            ->route('reports.scheduled', $organization)
            ->with('success', 'Запланированный отчет удален');
    }
}