<?php

namespace App\Jobs;

use App\Models\KnowledgeSource;
use App\Models\KnowledgeSyncLog;
use App\Services\Integrations\NotionService;
use App\Services\Integrations\GoogleDocsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncKnowledgeSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 минут
    public $tries = 3;

    protected KnowledgeSource $source;
    protected ?int $userId;

    /**
     * Create a new job instance.
     *
     * @param KnowledgeSource $source
     * @param int|null $userId ID пользователя, инициировавшего задачу
     */
    public function __construct(KnowledgeSource $source, ?int $userId = null)
    {
        $this->source = $source;
        $this->userId = $userId;
    }

    public function handle()
    {
        $log = KnowledgeSyncLog::create([
            'knowledge_source_id' => $this->source->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        try {
            $stats = [];
            
            switch ($this->source->type) {
                case 'notion':
                    $notionService = new NotionService();
                    $stats = $notionService->syncDatabase($this->source, $this->userId);
                    break;

                case 'google_docs':
                    $googleDocsService = new GoogleDocsService();
                    $stats = $googleDocsService->syncDocuments($this->source, $this->userId);
                    break;
                    
                case 'url':
                    $stats = $this->syncWebPages();
                    break;
                    
                case 'google_drive':
                    $stats = $this->syncGoogleDrive();
                    break;
                    
                case 'github':
                    $stats = $this->syncGitHub();
                    break;
                    
                default:
                    throw new \Exception('Неподдерживаемый тип источника: ' . $this->source->type);
            }

            // Обновляем лог синхронизации
            $log->update([
                'status' => empty($stats['errors']) ? 'success' : 'partial',
                'items_added' => $stats['added'] ?? 0,
                'items_updated' => $stats['updated'] ?? 0,
                'items_deleted' => $stats['deleted'] ?? 0,
                'details' => $stats,
                'completed_at' => now(),
            ]);

            // Обновляем источник
            $this->source->update([
                'last_sync_at' => now(),
                'next_sync_at' => $this->calculateNextSyncTime(),
                'sync_status' => [
                    'success' => true,
                    'last_error' => null,
                    'stats' => $stats,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Knowledge source sync failed', [
                'source_id' => $this->source->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $log->update([
                'status' => 'failed',
                'details' => ['error' => $e->getMessage()],
                'completed_at' => now(),
            ]);

            $this->source->update([
                'sync_status' => [
                    'success' => false,
                    'last_error' => $e->getMessage(),
                    'failed_at' => now(),
                ],
            ]);

            throw $e;
        }
    }

    protected function syncWebPages(): array
    {
        $stats = ['added' => 0, 'updated' => 0, 'deleted' => 0, 'errors' => []];
        $config = $this->source->config;
        $urls = $config['urls'] ?? [];

        foreach ($urls as $url) {
            try {
                $content = $this->fetchWebPage($url);
                
                if (empty($content)) {
                    continue;
                }

                // Проверяем существующий элемент
                $item = \App\Models\KnowledgeItem::where('knowledge_source_id', $this->source->id)
                    ->where('source_url', $url)
                    ->first();

                if ($item) {
                    // Проверяем изменения
                    if (md5($item->content) !== md5($content['body'])) {
                        $item->update([
                            'title' => $content['title'],
                            'content' => $content['body'],
                            'version' => $item->version + 1,
                            'last_synced_at' => now(),
                        ]);
                        $stats['updated']++;
                    }
                } else {
                    // Создаем новый элемент
                    \App\Models\KnowledgeItem::create([
                        'knowledge_base_id' => $this->source->knowledge_base_id,
                        'knowledge_source_id' => $this->source->id,
                        'type' => 'url',
                        'title' => $content['title'],
                        'content' => $content['body'],
                        'source_url' => $url,
                        'is_active' => true,
                        'last_synced_at' => now(),
                    ]);
                    $stats['added']++;
                }
            } catch (\Exception $e) {
                $stats['errors'][] = [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }

    protected function fetchWebPage(string $url): array
    {
        $client = new \GuzzleHttp\Client(['timeout' => 30]);
        $response = $client->get($url);
        $html = $response->getBody()->getContents();

        // Извлекаем заголовок
        preg_match('/<title>(.*?)<\/title>/is', $html, $matches);
        $title = $matches[1] ?? parse_url($url, PHP_URL_HOST);

        // Удаляем скрипты и стили
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);

        // Извлекаем основной контент
        $body = strip_tags($html);
        $body = preg_replace('/\s+/', ' ', $body);
        $body = trim($body);

        return [
            'title' => $title,
            'body' => $body,
        ];
    }

    protected function syncGoogleDrive(): array
    {
        // Заглушка для Google Drive синхронизации
        return ['added' => 0, 'updated' => 0, 'deleted' => 0];
    }

    protected function syncGitHub(): array
    {
        // Заглушка для GitHub синхронизации
        return ['added' => 0, 'updated' => 0, 'deleted' => 0];
    }

    protected function calculateNextSyncTime(): \Carbon\Carbon
    {
        $settings = $this->source->sync_settings;
        $interval = $settings['interval'] ?? 'daily';

        switch ($interval) {
            case 'hourly':
                return now()->addHour();
            case 'daily':
                return now()->addDay();
            case 'weekly':
                return now()->addWeek();
            case 'monthly':
                return now()->addMonth();
            default:
                return now()->addDay();
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Knowledge source sync job failed', [
            'source_id' => $this->source->id,
            'error' => $exception->getMessage(),
        ]);
    }
}