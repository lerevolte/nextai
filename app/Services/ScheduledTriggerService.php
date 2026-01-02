<?php

namespace App\Services;

use App\Models\BotFunction;
use App\Models\FunctionTrigger;
use App\Models\Conversation;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ScheduledTriggerService
{
    protected FunctionExecutionService $executionService;
    
    public function __construct(FunctionExecutionService $executionService)
    {
        $this->executionService = $executionService;
    }
    
    /**
     * Проверить и выполнить запланированные функции
     */
    public function checkAndExecuteScheduledFunctions(): void
    {
        $triggers = FunctionTrigger::where('type', 'schedule')
            ->where('is_active', true)
            ->with(['function' => function($query) {
                $query->where('is_active', true);
            }])
            ->get();
            
        foreach ($triggers as $trigger) {
            if (!$trigger->function) {
                continue;
            }
            
            try {
                if ($this->shouldExecute($trigger)) {
                    $this->executeScheduledFunction($trigger);
                }
            } catch (\Exception $e) {
                Log::error('Failed to execute scheduled function', [
                    'trigger_id' => $trigger->id,
                    'function_id' => $trigger->function_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Проверить, должна ли функция выполниться сейчас
     */
    protected function shouldExecute(FunctionTrigger $trigger): bool
    {
        $schedule = $this->getScheduleFromTrigger($trigger);
        
        if (!$schedule) {
            return false;
        }
        
        $lastRun = $schedule->last_run_at ? Carbon::parse($schedule->last_run_at) : null;
        $now = now();
        
        // Проверяем cron выражение
        if ($schedule->cron_expression) {
            try {
                $cron = new CronExpression($schedule->cron_expression);
                $nextRun = Carbon::instance($cron->getNextRunDate($lastRun ?? Carbon::now()->subMinute()));
                
                return $nextRun->lte($now);
            } catch (\Exception $e) {
                Log::error('Invalid cron expression', [
                    'trigger_id' => $trigger->id,
                    'cron' => $schedule->cron_expression
                ]);
                return false;
            }
        }
        
        // Проверяем простое расписание
        if ($schedule->time) {
            $scheduledTime = Carbon::parse($schedule->time, $schedule->timezone);
            
            // Проверяем дни недели
            if ($schedule->days_of_week && !empty($schedule->days_of_week)) {
                $dayOfWeek = $now->dayOfWeek;
                if (!in_array($dayOfWeek, $schedule->days_of_week)) {
                    return false;
                }
            }
            
            // Проверяем дни месяца
            if ($schedule->days_of_month && !empty($schedule->days_of_month)) {
                $dayOfMonth = $now->day;
                if (!in_array($dayOfMonth, $schedule->days_of_month)) {
                    return false;
                }
            }
            
            // Проверяем месяцы
            if ($schedule->months && !empty($schedule->months)) {
                $month = $now->month;
                if (!in_array($month, $schedule->months)) {
                    return false;
                }
            }
            
            // Проверяем время
            $todayScheduledTime = $now->copy()->setTimeFromTimeString($scheduledTime->format('H:i:s'));
            
            // Если последний запуск был сегодня, не запускаем снова
            if ($lastRun && $lastRun->isToday()) {
                return false;
            }
            
            return $now->gte($todayScheduledTime);
        }
        
        // Проверяем интервальное расписание
        if (isset($trigger->conditions['interval'])) {
            $interval = $trigger->conditions['interval'];
            $unit = $trigger->conditions['interval_unit'] ?? 'minutes';
            
            if (!$lastRun) {
                return true;
            }
            
            $nextRun = $lastRun->copy();
            
            switch ($unit) {
                case 'minutes':
                    $nextRun->addMinutes($interval);
                    break;
                case 'hours':
                    $nextRun->addHours($interval);
                    break;
                case 'days':
                    $nextRun->addDays($interval);
                    break;
                case 'weeks':
                    $nextRun->addWeeks($interval);
                    break;
                case 'months':
                    $nextRun->addMonths($interval);
                    break;
            }
            
            return $now->gte($nextRun);
        }
        
        return false;
    }
    
    /**
     * Выполнить запланированную функцию
     */
    protected function executeScheduledFunction(FunctionTrigger $trigger): void
    {
        $function = $trigger->function;
        
        Log::info('Executing scheduled function', [
            'function_id' => $function->id,
            'trigger_id' => $trigger->id
        ]);
        
        // Получаем или создаем диалог для расписания
        $conversation = $this->getScheduleConversation($function);
        
        // Создаем системное сообщение
        $message = $conversation->messages()->create([
            'role' => 'system',
            'content' => 'Scheduled function execution triggered',
            'metadata' => [
                'type' => 'scheduled_trigger',
                'trigger_id' => $trigger->id,
                'scheduled_at' => now()->toIso8601String()
            ]
        ]);
        
        // Получаем параметры для выполнения
        $parameters = $this->getScheduledParameters($trigger);
        
        try {
            // Выполняем функцию
            $result = $this->executionService->execute(
                $function,
                $parameters,
                $conversation,
                $message
            );
            
            // Обновляем время последнего запуска
            $this->updateLastRun($trigger);
            
            // Логируем успешное выполнение
            Log::info('Scheduled function executed successfully', [
                'function_id' => $function->id,
                'trigger_id' => $trigger->id,
                'result' => $result
            ]);
            
        } catch (\Exception $e) {
            Log::error('Scheduled function execution failed', [
                'function_id' => $function->id,
                'trigger_id' => $trigger->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Обработка ошибок согласно настройкам
            $this->handleExecutionError($trigger, $e);
        }
    }
    
    /**
     * Получить расписание из триггера
     */
    protected function getScheduleFromTrigger(FunctionTrigger $trigger)
    {
        return \DB::table('function_schedules')
            ->where('trigger_id', $trigger->id)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Получить диалог для расписания
     */
    protected function getScheduleConversation(BotFunction $function): Conversation
    {
        $bot = $function->bot;
        
        // Ищем существующий диалог для расписания
        $conversation = Conversation::where('bot_id', $bot->id)
            ->where('channel', 'schedule')
            ->where('external_id', 'schedule_' . $function->id)
            ->first();
            
        if (!$conversation) {
            $conversation = Conversation::create([
                'bot_id' => $bot->id,
                'channel' => 'schedule',
                'external_id' => 'schedule_' . $function->id,
                'user_name' => 'System Scheduler',
                'metadata' => [
                    'type' => 'scheduled_execution',
                    'function_id' => $function->id
                ]
            ]);
        }
        
        return $conversation;
    }
    
    /**
     * Получить параметры для запланированного выполнения
     */
    protected function getScheduledParameters(FunctionTrigger $trigger): array
    {
        $parameters = [];
        
        // Получаем статичные параметры из конфигурации
        if (isset($trigger->conditions['parameters'])) {
            $parameters = $trigger->conditions['parameters'];
        }
        
        // Добавляем динамические параметры
        $parameters['_scheduled_at'] = now()->toIso8601String();
        $parameters['_trigger_id'] = $trigger->id;
        
        // Заменяем переменные времени
        $parameters = $this->replaceTimeVariables($parameters);
        
        return $parameters;
    }
    
    /**
     * Заменить временные переменные в параметрах
     */
    protected function replaceTimeVariables(array $parameters): array
    {
        $now = now();
        
        $replacements = [
            '{now}' => $now->toIso8601String(),
            '{today}' => $now->format('Y-m-d'),
            '{yesterday}' => $now->subDay()->format('Y-m-d'),
            '{tomorrow}' => $now->addDay()->format('Y-m-d'),
            '{current_month}' => $now->format('Y-m'),
            '{current_year}' => $now->format('Y'),
            '{timestamp}' => $now->timestamp,
        ];
        
        array_walk_recursive($parameters, function(&$value) use ($replacements) {
            if (is_string($value)) {
                $value = str_replace(
                    array_keys($replacements),
                    array_values($replacements),
                    $value
                );
            }
        });
        
        return $parameters;
    }
    
    /**
     * Обновить время последнего запуска
     */
    protected function updateLastRun(FunctionTrigger $trigger): void
    {
        $now = now();
        
        \DB::table('function_schedules')
            ->where('trigger_id', $trigger->id)
            ->update([
                'last_run_at' => $now,
                'next_run_at' => $this->calculateNextRun($trigger, $now),
                'updated_at' => $now
            ]);
    }
    
    /**
     * Рассчитать время следующего запуска
     */
    protected function calculateNextRun(FunctionTrigger $trigger, Carbon $fromTime): ?Carbon
    {
        $schedule = $this->getScheduleFromTrigger($trigger);
        
        if (!$schedule) {
            return null;
        }
        
        if ($schedule->cron_expression) {
            try {
                $cron = new CronExpression($schedule->cron_expression);
                return Carbon::instance($cron->getNextRunDate($fromTime));
            } catch (\Exception $e) {
                return null;
            }
        }
        
        if (isset($trigger->conditions['interval'])) {
            $interval = $trigger->conditions['interval'];
            $unit = $trigger->conditions['interval_unit'] ?? 'minutes';
            
            $nextRun = $fromTime->copy();
            
            switch ($unit) {
                case 'minutes':
                    $nextRun->addMinutes($interval);
                    break;
                case 'hours':
                    $nextRun->addHours($interval);
                    break;
                case 'days':
                    $nextRun->addDays($interval);
                    break;
                case 'weeks':
                    $nextRun->addWeeks($interval);
                    break;
                case 'months':
                    $nextRun->addMonths($interval);
                    break;
            }
            
            return $nextRun;
        }
        
        if ($schedule->time) {
            // Для ежедневного расписания - следующий день в то же время
            $nextRun = $fromTime->copy()->addDay();
            $nextRun->setTimeFromTimeString($schedule->time);
            
            // Учитываем дни недели
            if ($schedule->days_of_week && !empty($schedule->days_of_week)) {
                while (!in_array($nextRun->dayOfWeek, $schedule->days_of_week)) {
                    $nextRun->addDay();
                }
            }
            
            return $nextRun;
        }
        
        return null;
    }
    
    /**
     * Обработать ошибку выполнения
     */
    protected function handleExecutionError(FunctionTrigger $trigger, \Exception $exception): void
    {
        $errorHandling = $trigger->conditions['error_handling'] ?? 'log';
        
        switch ($errorHandling) {
            case 'retry':
                $this->scheduleRetry($trigger, $exception);
                break;
                
            case 'disable':
                $this->disableSchedule($trigger, $exception);
                break;
                
            case 'notify':
                $this->notifyAdministrator($trigger, $exception);
                break;
                
            case 'log':
            default:
                // Уже залогировано выше
                break;
        }
        
        // Увеличиваем счетчик ошибок
        $this->incrementErrorCount($trigger);
    }
    
    /**
     * Запланировать повторную попытку
     */
    protected function scheduleRetry(FunctionTrigger $trigger, \Exception $exception): void
    {
        $retryCount = Cache::get("schedule_retry:{$trigger->id}", 0);
        $maxRetries = $trigger->conditions['max_retries'] ?? 3;
        
        if ($retryCount >= $maxRetries) {
            Log::error('Max retries exceeded for scheduled function', [
                'trigger_id' => $trigger->id,
                'retries' => $retryCount
            ]);
            
            // Отключаем расписание после превышения попыток
            $this->disableSchedule($trigger, $exception);
            return;
        }
        
        $retryDelay = $trigger->conditions['retry_delay'] ?? 5; // минут
        
        Cache::put(
            "schedule_retry:{$trigger->id}",
            $retryCount + 1,
            now()->addHour()
        );
        
        // Планируем повторную попытку
        \DB::table('function_schedules')
            ->where('trigger_id', $trigger->id)
            ->update([
                'next_run_at' => now()->addMinutes($retryDelay),
                'updated_at' => now()
            ]);
            
        Log::info('Scheduled retry for failed function', [
            'trigger_id' => $trigger->id,
            'retry_count' => $retryCount + 1,
            'next_retry' => now()->addMinutes($retryDelay)
        ]);
    }
    
    /**
     * Отключить расписание
     */
    protected function disableSchedule(FunctionTrigger $trigger, \Exception $exception): void
    {
        \DB::table('function_schedules')
            ->where('trigger_id', $trigger->id)
            ->update([
                'is_active' => false,
                'updated_at' => now()
            ]);
            
        $trigger->update(['is_active' => false]);
        
        Log::warning('Scheduled function disabled due to error', [
            'trigger_id' => $trigger->id,
            'error' => $exception->getMessage()
        ]);
        
        // Уведомляем администратора об отключении
        $this->notifyAdministrator($trigger, $exception, 'Schedule disabled');
    }
    
    /**
     * Уведомить администратора об ошибке
     */
    protected function notifyAdministrator(FunctionTrigger $trigger, \Exception $exception, string $subject = 'Schedule error'): void
    {
        $function = $trigger->function;
        $bot = $function->bot;
        $organization = $bot->organization;
        
        // Получаем email администратора
        $adminEmail = $organization->settings['admin_email'] 
            ?? $organization->owner->email 
            ?? config('mail.admin_email');
            
        if (!$adminEmail) {
            return;
        }
        
        try {
            \Mail::send('emails.schedule-error', [
                'organization' => $organization->name,
                'bot' => $bot->name,
                'function' => $function->display_name,
                'trigger' => $trigger->name,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'time' => now()->format('Y-m-d H:i:s')
            ], function ($message) use ($adminEmail, $subject) {
                $message->to($adminEmail)
                    ->subject($subject);
            });
        } catch (\Exception $e) {
            Log::error('Failed to send admin notification', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Увеличить счетчик ошибок
     */
    protected function incrementErrorCount(FunctionTrigger $trigger): void
    {
        \DB::table('function_schedules')
            ->where('trigger_id', $trigger->id)
            ->increment('error_count');
    }
    
    /**
     * Получить статистику выполнения расписаний
     */
    public function getScheduleStatistics(BotFunction $function): array
    {
        $triggers = $function->triggers()
            ->where('type', 'schedule')
            ->get();
            
        $statistics = [];
        
        foreach ($triggers as $trigger) {
            $schedule = $this->getScheduleFromTrigger($trigger);
            
            if (!$schedule) {
                continue;
            }
            
            $executions = \DB::table('function_executions')
                ->where('function_id', $function->id)
                ->whereJsonContains('metadata->trigger_id', $trigger->id)
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                    AVG(TIMESTAMPDIFF(SECOND, created_at, executed_at)) as avg_duration
                ')
                ->first();
                
            $statistics[] = [
                'trigger_id' => $trigger->id,
                'trigger_name' => $trigger->name,
                'is_active' => $schedule->is_active,
                'last_run' => $schedule->last_run_at,
                'next_run' => $schedule->next_run_at,
                'total_executions' => $executions->total,
                'successful_executions' => $executions->successful,
                'failed_executions' => $executions->failed,
                'average_duration' => round($executions->avg_duration ?? 0, 2),
                'error_count' => $schedule->error_count ?? 0,
                'schedule' => [
                    'cron' => $schedule->cron_expression,
                    'time' => $schedule->time,
                    'days_of_week' => $schedule->days_of_week,
                    'timezone' => $schedule->timezone
                ]
            ];
        }
        
        return $statistics;
    }
}