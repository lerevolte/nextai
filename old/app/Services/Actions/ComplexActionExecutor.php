<?php

namespace App\Services\Actions;

use App\Models\Conversation;
use App\Models\FunctionAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ComplexActionExecutor
{
    /**
     * Получить статус заказа
     */
    public function getOrderStatus(FunctionAction $action, array $params): array
    {
        $orderId = $params['order_id'] ?? null;
        
        if (!$orderId) {
            throw new \Exception('Номер заказа не указан');
        }
        
        // Запрос к базе данных или API
        $order = DB::table('orders')
            ->where('id', $orderId)
            ->orWhere('number', $orderId)
            ->first();
            
        if (!$order) {
            return [
                'success' => false,
                'error' => "Заказ {$orderId} не найден"
            ];
        }
        
        // Получаем детали доставки
        $delivery = DB::table('deliveries')
            ->where('order_id', $order->id)
            ->first();
            
        return [
            'success' => true,
            'data' => [
                'order_id' => $order->number,
                'status' => $this->translateStatus($order->status),
                'status_code' => $order->status,
                'created_at' => $order->created_at,
                'total' => $order->total,
                'delivery_date' => $delivery->estimated_date ?? 'Уточняется',
                'tracking_number' => $delivery->tracking_number ?? null,
                'items_count' => DB::table('order_items')
                    ->where('order_id', $order->id)
                    ->count(),
            ]
        ];
    }
    
    /**
     * Проверить доступность времени для записи
     */
    public function checkAvailability(FunctionAction $action, array $params): array
    {
        $date = $params['date'] ?? null;
        $time = $params['time'] ?? null;
        $service = $params['service'] ?? null;
        $specialist = $params['specialist'] ?? null;
        
        // Проверяем занятость
        $isBooked = DB::table('appointments')
            ->where('date', $date)
            ->where('time', $time)
            ->when($specialist, function ($query, $specialist) {
                return $query->where('specialist_id', $specialist);
            })
            ->exists();
            
        if ($isBooked) {
            // Предлагаем альтернативные времена
            $alternatives = $this->findAlternativeSlots($date, $time, $specialist);
            
            return [
                'success' => false,
                'available' => false,
                'alternatives' => $alternatives,
                'message' => 'Это время занято. Доступные альтернативы: ' . 
                            implode(', ', array_column($alternatives, 'time'))
            ];
        }
        
        return [
            'success' => true,
            'available' => true,
            'message' => 'Время доступно для записи'
        ];
    }
    
    /**
     * Рассчитать стоимость
     */
    public function calculatePrice(FunctionAction $action, array $params): array
    {
        $product = $params['product'] ?? null;
        $quantity = $params['quantity'] ?? 1;
        $options = $params['options'] ?? [];
        
        // Базовая цена
        $basePrice = $this->getBasePrice($product);
        
        if (!$basePrice) {
            return [
                'success' => false,
                'error' => 'Товар/услуга не найдены'
            ];
        }
        
        // Рассчитываем стоимость с учетом опций
        $optionsPrice = 0;
        $includes = [];
        
        foreach ($options as $option) {
            $optionData = $this->getOptionPrice($option);
            if ($optionData) {
                $optionsPrice += $optionData['price'];
                $includes[] = $optionData['name'];
            }
        }
        
        $total = ($basePrice + $optionsPrice) * $quantity;
        
        // Применяем скидки
        $discount = $this->calculateDiscount($total, $params);
        $finalPrice = $total - $discount;
        
        return [
            'success' => true,
            'data' => [
                'product' => $product,
                'quantity' => $quantity,
                'base_price' => $basePrice,
                'options_price' => $optionsPrice,
                'subtotal' => $total,
                'discount' => $discount,
                'price' => $finalPrice,
                'includes' => implode(', ', $includes),
                'duration' => $this->getEstimatedDuration($product, $options),
                'currency' => 'руб.'
            ]
        ];
    }
    
    /**
     * Передать оператору
     */
    public function transferToOperator(FunctionAction $action, array $params, Conversation $conversation): array
    {
        $priority = $action->config['priority'] ?? 'normal';
        $department = $action->config['department'] ?? 'general';
        $reason = $params['reason'] ?? 'Клиент запросил оператора';
        
        // Меняем статус диалога
        $conversation->update([
            'status' => 'waiting_operator',
            'metadata' => array_merge($conversation->metadata ?? [], [
                'transfer_reason' => $reason,
                'transfer_priority' => $priority,
                'transfer_department' => $department,
                'transferred_at' => now()->toIso8601String(),
            ])
        ]);
        
        // Отправляем уведомление операторам
        $this->notifyOperators($conversation, $priority, $department);
        
        // Создаем задачу в CRM если настроено
        if ($action->config['create_task'] ?? false) {
            $this->createOperatorTask($conversation, $reason, $priority);
        }
        
        return [
            'success' => true,
            'data' => [
                'status' => 'transferred',
                'priority' => $priority,
                'department' => $department,
                'estimated_wait' => $this->getEstimatedWaitTime($priority, $department),
            ]
        ];
    }
    
    /**
     * Анализ тональности
     */
    public function analyzeSentiment(FunctionAction $action, array $params, Conversation $conversation): array
    {
        $text = $params['complaint_text'] ?? $params['message'] ?? '';
        
        // Используем AI для анализа
        $sentiment = app(AIService::class)->analyzeSentiment($text);
        
        // Определяем уровень критичности
        $severity = 'low';
        if ($sentiment['score'] < -0.5) {
            $severity = 'high';
        } elseif ($sentiment['score'] < -0.2) {
            $severity = 'medium';
        }
        
        // Ключевые слова проблемы
        $keywords = $this->extractProblemKeywords($text);
        
        return [
            'success' => true,
            'data' => [
                'sentiment' => $sentiment['label'], // positive/negative/neutral
                'score' => $sentiment['score'], // -1 to 1
                'severity' => $severity,
                'keywords' => $keywords,
                'requires_attention' => $severity === 'high',
                'suggested_actions' => $this->suggestActions($sentiment, $keywords),
            ]
        ];
    }
    
    // Вспомогательные методы
    
    private function translateStatus(string $status): string
    {
        $statuses = [
            'pending' => '⏳ В обработке',
            'confirmed' => '✅ Подтвержден',
            'processing' => '⚙️ Обрабатывается',
            'shipped' => '🚚 Отправлен',
            'delivered' => '📦 Доставлен',
            'cancelled' => '❌ Отменен',
            'refunded' => '💸 Возвращен',
        ];
        
        return $statuses[$status] ?? $status;
    }
    
    private function findAlternativeSlots($date, $time, $specialist): array
    {
        // Логика поиска свободных слотов
        $slots = [];
        $baseTime = \Carbon\Carbon::parse("$date $time");
        
        for ($i = -2; $i <= 2; $i++) {
            if ($i === 0) continue;
            
            $checkTime = $baseTime->copy()->addHours($i);
            $isAvailable = !DB::table('appointments')
                ->where('date', $checkTime->format('Y-m-d'))
                ->where('time', $checkTime->format('H:i'))
                ->when($specialist, function ($query, $specialist) {
                    return $query->where('specialist_id', $specialist);
                })
                ->exists();
                
            if ($isAvailable) {
                $slots[] = [
                    'date' => $checkTime->format('Y-m-d'),
                    'time' => $checkTime->format('H:i'),
                    'available' => true
                ];
            }
            
            if (count($slots) >= 3) break;
        }
        
        return $slots;
    }
    
    private function getEstimatedWaitTime($priority, $department): string
    {
        $baseTime = match($department) {
            'sales' => 5,
            'support' => 10,
            'technical' => 15,
            default => 10
        };
        
        $multiplier = match($priority) {
            'high' => 0.5,
            'normal' => 1,
            'low' => 2,
            default => 1
        };
        
        $minutes = round($baseTime * $multiplier);
        
        return $minutes < 60 
            ? "{$minutes} минут" 
            : round($minutes / 60) . " час.";
    }
    
    private function extractProblemKeywords(string $text): array
    {
        $problemWords = [
            'не работает', 'сломался', 'ошибка', 'проблема', 
            'плохо', 'ужасно', 'возврат', 'обман', 'мошенники',
            'долго', 'задержка', 'потерялся', 'испорчен'
        ];
        
        $found = [];
        foreach ($problemWords as $word) {
            if (mb_stripos($text, $word) !== false) {
                $found[] = $word;
            }
        }
        
        return $found;
    }
    
    private function suggestActions($sentiment, $keywords): array
    {
        $actions = [];
        
        if ($sentiment['score'] < -0.5) {
            $actions[] = 'Срочно передать менеджеру';
            $actions[] = 'Предложить компенсацию';
        }
        
        if (in_array('возврат', $keywords)) {
            $actions[] = 'Инициировать процесс возврата';
        }
        
        if (in_array('задержка', $keywords) || in_array('долго', $keywords)) {
            $actions[] = 'Проверить статус и ускорить процесс';
            $actions[] = 'Предоставить точные сроки';
        }
        
        return $actions;
    }
}