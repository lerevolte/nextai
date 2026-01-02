<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Bot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportExportService
{
    /**
     * Генерация отчета в различных форматах
     */
    public function generateReport(Organization $organization, array $options = []): string
    {
        $format = $options['format'] ?? 'pdf';
        $period = $options['period'] ?? 30;
        $botId = $options['bot_id'] ?? null;
        $includeCharts = $options['include_charts'] ?? true;
        
        $startDate = Carbon::now()->subDays($period);
        $endDate = Carbon::now();
        
        // Собираем данные для отчета
        $reportData = $this->collectReportData($organization, $startDate, $endDate, $botId);
        
        // Генерируем отчет в нужном формате
        switch ($format) {
            case 'pdf':
                return $this->generatePdfReport($reportData, $options);
            case 'excel':
                return $this->generateExcelReport($reportData, $options);
            case 'csv':
                return $this->generateCsvReport($reportData, $options);
            case 'json':
                return $this->generateJsonReport($reportData);
            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
    }

    /**
     * Сбор данных для отчета
     */
    protected function collectReportData(Organization $organization, Carbon $startDate, Carbon $endDate, ?int $botId = null): array
    {
        $data = [
            'organization' => $organization->only(['id', 'name']),
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'days' => $startDate->diffInDays($endDate)
            ],
            'generated_at' => now()->toDateTimeString(),
            'metrics' => $this->collectMetrics($organization, $startDate, $endDate, $botId),
            'conversations' => $this->collectConversationData($organization, $startDate, $endDate, $botId),
            'messages' => $this->collectMessageData($organization, $startDate, $endDate, $botId),
            'channels' => $this->collectChannelData($organization, $startDate, $endDate, $botId),
            'bots_performance' => $this->collectBotPerformance($organization, $startDate, $endDate, $botId),
            'user_satisfaction' => $this->collectSatisfactionData($organization, $startDate, $endDate, $botId),
            'ai_usage' => $this->collectAiUsageData($organization, $startDate, $endDate, $botId),
            'trends' => $this->collectTrendData($organization, $startDate, $endDate, $botId)
        ];
        
        // Добавляем данные A/B тестов если есть
        if ($organization->abTests()->exists()) {
            $data['ab_tests'] = $this->collectAbTestData($organization, $startDate, $endDate, $botId);
        }
        
        return $data;
    }

    /**
     * Генерация PDF отчета
     */
    protected function generatePdfReport(array $data, array $options): string
    {
        $pdf = PDF::loadView('reports.comprehensive', [
            'data' => $data,
            'options' => $options,
            'charts' => $options['include_charts'] ? $this->generateChartImages($data) : []
        ]);
        
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,
            'defaultFont' => 'sans-serif'
        ]);
        
        $filename = 'report_' . $data['organization']['id'] . '_' . now()->format('Y-m-d_His') . '.pdf';
        $path = 'reports/' . $filename;
        
        Storage::put($path, $pdf->output());
        
        return Storage::url($path);
    }

    /**
     * Генерация Excel отчета с графиками
     */
    protected function generateExcelReport(array $data, array $options): string
    {
        $spreadsheet = new Spreadsheet();
        
        // Сводка
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Сводка');
        $this->fillSummarySheet($summarySheet, $data);
        
        // Детальные метрики
        $metricsSheet = $spreadsheet->createSheet();
        $metricsSheet->setTitle('Метрики');
        $this->fillMetricsSheet($metricsSheet, $data['metrics']);
        
        // Диалоги
        $conversationsSheet = $spreadsheet->createSheet();
        $conversationsSheet->setTitle('Диалоги');
        $this->fillConversationsSheet($conversationsSheet, $data['conversations']);
        
        // Производительность ботов
        $botsSheet = $spreadsheet->createSheet();
        $botsSheet->setTitle('Боты');
        $this->fillBotsSheet($botsSheet, $data['bots_performance']);
        
        // Каналы
        $channelsSheet = $spreadsheet->createSheet();
        $channelsSheet->setTitle('Каналы');
        $this->fillChannelsSheet($channelsSheet, $data['channels']);
        
        // Добавляем графики если включены
        if ($options['include_charts'] ?? true) {
            $this->addChartsToExcel($spreadsheet, $data);
        }
        
        // Сохраняем файл
        $writer = new Xlsx($spreadsheet);
        $filename = 'report_' . $data['organization']['id'] . '_' . now()->format('Y-m-d_His') . '.xlsx';
        $path = storage_path('app/reports/' . $filename);
        
        $writer->save($path);
        
        return Storage::url('reports/' . $filename);
    }

    /**
     * Заполнение листа сводки
     */
    protected function fillSummarySheet($sheet, array $data): void
    {
        // Заголовок
        $sheet->setCellValue('A1', 'Отчет по чат-ботам');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        
        // Информация об организации
        $sheet->setCellValue('A3', 'Организация:');
        $sheet->setCellValue('B3', $data['organization']['name']);
        $sheet->setCellValue('A4', 'Период:');
        $sheet->setCellValue('B4', $data['period']['start'] . ' - ' . $data['period']['end']);
        $sheet->setCellValue('A5', 'Сформирован:');
        $sheet->setCellValue('B5', $data['generated_at']);
        
        // Ключевые метрики
        $sheet->setCellValue('A7', 'Ключевые показатели');
        $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(14);
        
        $row = 9;
        $sheet->setCellValue('A' . $row, 'Показатель');
        $sheet->setCellValue('B' . $row, 'Значение');
        $sheet->setCellValue('C' . $row, 'Изменение');
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
        
        $row++;
        foreach ($data['metrics']['summary'] as $key => $value) {
            $sheet->setCellValue('A' . $row, $this->translateMetricName($key));
            $sheet->setCellValue('B' . $row, $value['value']);
            $sheet->setCellValue('C' . $row, $value['trend'] . '%');
            
            // Цветовая индикация трендов
            if ($value['trend'] > 0) {
                $sheet->getStyle('C' . $row)->getFont()->getColor()->setRGB('00AA00');
            } elseif ($value['trend'] < 0) {
                $sheet->getStyle('C' . $row)->getFont()->getColor()->setRGB('FF0000');
            }
            
            $row++;
        }
        
        // Автоматическая ширина колонок
        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * Сбор основных метрик
     */
    protected function collectMetrics(Organization $organization, Carbon $startDate, Carbon $endDate, ?int $botId = null): array
    {
        $query = DB::table('conversations')
            ->join('bots', 'conversations.bot_id', '=', 'bots.id')
            ->where('bots.organization_id', $organization->id)
            ->whereBetween('conversations.created_at', [$startDate, $endDate]);
        
        if ($botId) {
            $query->where('bots.id', $botId);
        }
        
        return [
            'summary' => [
                'total_conversations' => [
                    'value' => $query->count(),
                    'trend' => $this->calculateTrend('conversations', $organization, $startDate, $endDate, $botId)
                ],
                'unique_users' => [
                    'value' => $query->distinct('conversations.external_id')->count('conversations.external_id'),
                    'trend' => $this->calculateTrend('users', $organization, $startDate, $endDate, $botId)
                ],
                'avg_response_time' => [
                    'value' => round($this->getAverageResponseTime($organization, $startDate, $endDate, $botId), 2),
                    'trend' => $this->calculateTrend('response_time', $organization, $startDate, $endDate, $botId)
                ],
                'success_rate' => [
                    'value' => $this->getSuccessRate($organization, $startDate, $endDate, $botId),
                    'trend' => $this->calculateTrend('success_rate', $organization, $startDate, $endDate, $botId)
                ],
                'satisfaction_score' => [
                    'value' => $this->getSatisfactionScore($organization, $startDate, $endDate, $botId),
                    'trend' => $this->calculateTrend('satisfaction', $organization, $startDate, $endDate, $botId)
                ]
            ]
        ];
    }

    /**
     * Генерация CSV отчета
     */
    protected function generateCsvReport(array $data, array $options): string
    {
        $csv = [];
        
        // Заголовок
        $csv[] = ['Отчет по чат-ботам'];
        $csv[] = ['Организация', $data['organization']['name']];
        $csv[] = ['Период', $data['period']['start'] . ' - ' . $data['period']['end']];
        $csv[] = ['Сформирован', $data['generated_at']];
        $csv[] = [];
        
        // Метрики
        $csv[] = ['Ключевые показатели'];
        $csv[] = ['Показатель', 'Значение', 'Изменение %'];
        foreach ($data['metrics']['summary'] as $key => $metric) {
            $csv[] = [$this->translateMetricName($key), $metric['value'], $metric['trend']];
        }
        $csv[] = [];
        
        // Производительность ботов
        $csv[] = ['Производительность ботов'];
        $csv[] = ['Бот', 'Диалоги', 'Сообщения', 'Ср. время ответа', 'Успешность %'];
        foreach ($data['bots_performance'] as $bot) {
            $csv[] = [
                $bot['name'],
                $bot['conversations'],
                $bot['messages'],
                $bot['avg_response_time'],
                $bot['success_rate']
            ];
        }
        
        // Создаем CSV файл
        $filename = 'report_' . $data['organization']['id'] . '_' . now()->format('Y-m-d_His') . '.csv';
        $path = 'reports/' . $filename;
        
        $handle = fopen(storage_path('app/' . $path), 'w');
        foreach ($csv as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        
        return Storage::url($path);
    }

    /**
     * Планировщик автоматических отчетов
     */
    public function scheduleReport(Organization $organization, array $config): void
    {
        DB::table('scheduled_reports')->insert([
            'organization_id' => $organization->id,
            'name' => $config['name'],
            'frequency' => $config['frequency'], // daily, weekly, monthly
            'format' => $config['format'],
            'recipients' => json_encode($config['recipients']),
            'config' => json_encode($config['options']),
            'next_run_at' => $this->calculateNextRunTime($config['frequency']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Выполнение запланированных отчетов
     */
    public function runScheduledReports(): void
    {
        $reports = DB::table('scheduled_reports')
            ->where('is_active', true)
            ->where('next_run_at', '<=', now())
            ->get();
        
        foreach ($reports as $report) {
            try {
                $organization = Organization::find($report->organization_id);
                $config = json_decode($report->config, true);
                
                // Генерируем отчет
                $url = $this->generateReport($organization, array_merge($config, [
                    'format' => $report->format
                ]));
                
                // Отправляем получателям
                $this->sendReportToRecipients($url, json_decode($report->recipients), $report->name);
                
                // Обновляем время следующего запуска
                DB::table('scheduled_reports')
                    ->where('id', $report->id)
                    ->update([
                        'last_run_at' => now(),
                        'next_run_at' => $this->calculateNextRunTime($report->frequency)
                    ]);
                    
            } catch (\Exception $e) {
                Log::error('Failed to generate scheduled report', [
                    'report_id' => $report->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    protected function translateMetricName(string $key): string
    {
        $translations = [
            'total_conversations' => 'Всего диалогов',
            'unique_users' => 'Уникальных пользователей',
            'avg_response_time' => 'Среднее время ответа (сек)',
            'success_rate' => 'Успешность (%)',
            'satisfaction_score' => 'Удовлетворенность'
        ];
        
        return $translations[$key] ?? $key;
    }
}