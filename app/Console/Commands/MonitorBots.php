<?
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bot;
use App\Models\Conversation;

class MonitorBots extends Command
{
    protected $signature = 'bots:monitor 
                          {--realtime : Show realtime metrics}
                          {--alerts : Check for alerts}';
    
    protected $description = 'Monitor bot performance and health';

    public function handle()
    {
        if ($this->option('realtime')) {
            $this->monitorRealtime();
        } elseif ($this->option('alerts')) {
            $this->checkAlerts();
        } else {
            $this->showBotStatus();
        }
        
        return 0;
    }
    
    protected function showBotStatus()
    {
        $bots = Bot::where('is_active', true)
            ->withCount([
                'conversations as active_conversations' => function ($query) {
                    $query->where('status', 'active');
                },
                'conversations as total_today' => function ($query) {
                    $query->whereDate('created_at', today());
                }
            ])
            ->get();
        
        $this->info('Bot Status Dashboard');
        $this->newLine();
        
        foreach ($bots as $bot) {
            $status = $this->getBotStatus($bot);
            $statusIcon = $status === 'healthy' ? '🟢' : ($status === 'warning' ? '🟡' : '🔴');
            
            $this->line("{$statusIcon} {$bot->name}");
            $this->line("  Active conversations: {$bot->active_conversations}");
            $this->line("  Today's total: {$bot->total_today}");
            
            // Получаем среднее время ответа
            $avgResponseTime = \DB::table('messages')
                ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
                ->where('conversations.bot_id', $bot->id)
                ->where('messages.role', 'assistant')
                ->where('messages.created_at', '>=', now()->subHour())
                ->avg('messages.response_time');
            
            $this->line("  Avg response time (1h): " . round($avgResponseTime ?? 0, 2) . "s");
            $this->newLine();
        }
    }
    
    protected function monitorRealtime()
    {
        $this->info('Starting realtime monitoring... (Press Ctrl+C to stop)');
        
        while (true) {
            $this->output->write("\033[2J\033[H"); // Clear screen
            
            $this->info('🔴 LIVE Bot Monitoring - ' . now()->format('H:i:s'));
            $this->newLine();
            
            $metrics = $this->collectRealtimeMetrics();
            
            $this->table(
                ['Metric', 'Value', 'Status'],
                [
                    ['Active Conversations', $metrics['active_conversations'], $this->getStatusIndicator($metrics['active_conversations'], 100, 200)],
                    ['Messages/min', $metrics['messages_per_minute'], $this->getStatusIndicator($metrics['messages_per_minute'], 50, 100)],
                    ['Avg Response Time', $metrics['avg_response_time'] . 's', $this->getStatusIndicator($metrics['avg_response_time'], 2, 5, true)],
                    ['Error Rate', $metrics['error_rate'] . '%', $this->getStatusIndicator($metrics['error_rate'], 5, 10, true)],
                    ['CPU Usage', $metrics['cpu_usage'] . '%', $this->getStatusIndicator($metrics['cpu_usage'], 70, 90, true)],
                    ['Memory Usage', $metrics['memory_usage'] . '%', $this->getStatusIndicator($metrics['memory_usage'], 70, 85, true)],
                ]
            );
            
            sleep(5); // Update every 5 seconds
        }
    }
    
    protected function checkAlerts()
    {
        $alerts = [];
        
        // Проверяем время ответа
        $slowBots = Bot::where('is_active', true)
            ->whereHas('conversations.messages', function ($query) {
                $query->where('role', 'assistant')
                    ->where('created_at', '>=', now()->subHour())
                    ->having(\DB::raw('AVG(response_time)'), '>', 5);
            })
            ->get();
        
        foreach ($slowBots as $bot) {
            $alerts[] = [
                'type' => 'warning',
                'bot' => $bot->name,
                'message' => 'Slow response time detected'
            ];
        }
        
        // Проверяем зависшие диалоги
        $stuckConversations = Conversation::where('status', 'active')
            ->where('last_message_at', '<', now()->subHours(2))
            ->count();
        
        if ($stuckConversations > 0) {
            $alerts[] = [
                'type' => 'warning',
                'bot' => 'System',
                'message' => "{$stuckConversations} conversations stuck for >2 hours"
            ];
        }
        
        // Проверяем ошибки
        $recentErrors = \DB::table('messages')
            ->where('created_at', '>=', now()->subHour())
            ->whereNotNull('metadata->error')
            ->count();
        
        if ($recentErrors > 10) {
            $alerts[] = [
                'type' => 'critical',
                'bot' => 'System',
                'message' => "{$recentErrors} errors in the last hour"
            ];
        }
        
        if (empty($alerts)) {
            $this->info('✅ No alerts - all systems operational');
        } else {
            $this->warn('⚠️ ' . count($alerts) . ' alerts found:');
            
            foreach ($alerts as $alert) {
                $icon = $alert['type'] === 'critical' ? '🔴' : '🟡';
                $this->line("{$icon} [{$alert['bot']}] {$alert['message']}");
            }
        }
    }
    
    protected function getBotStatus(Bot $bot): string
    {
        // Логика определения статуса бота
        if ($bot->active_conversations > 50) {
            return 'overloaded';
        }
        
        $avgResponseTime = \DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.bot_id', $bot->id)
            ->where('messages.created_at', '>=', now()->subHour())
            ->avg('messages.response_time');
        
        if ($avgResponseTime > 5) {
            return 'warning';
        }
        
        return 'healthy';
    }
    
    protected function collectRealtimeMetrics(): array
    {
        return [
            'active_conversations' => Conversation::where('status', 'active')->count(),
            'messages_per_minute' => \DB::table('messages')
                ->where('created_at', '>=', now()->subMinute())
                ->count(),
            'avg_response_time' => round(\DB::table('messages')
                ->where('role', 'assistant')
                ->where('created_at', '>=', now()->subMinutes(5))
                ->avg('response_time') ?? 0, 2),
            'error_rate' => $this->calculateErrorRate(),
            'cpu_usage' => round(sys_getloadavg()[0] * 10, 2),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ];
    }
    
    protected function calculateErrorRate(): float
    {
        $total = \DB::table('messages')
            ->where('created_at', '>=', now()->subHour())
            ->count();
        
        if ($total === 0) return 0;
        
        $errors = \DB::table('messages')
            ->where('created_at', '>=', now()->subHour())
            ->whereNotNull('metadata->error')
            ->count();
        
        return round(($errors / $total) * 100, 2);
    }
    
    protected function getStatusIndicator($value, $warningThreshold, $criticalThreshold, $inverse = false): string
    {
        if ($inverse) {
            if ($value >= $criticalThreshold) return '🔴';
            if ($value >= $warningThreshold) return '🟡';
            return '🟢';
        } else {
            if ($value <= $warningThreshold) return '🔴';
            if ($value <= $criticalThreshold) return '🟡';
            return '🟢';
        }
    }
}