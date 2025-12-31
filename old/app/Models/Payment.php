<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'payment_id',
        'status',
        'amount',
        'currency',
        'description',
        'payment_type',
        'subscription_id',
        'payment_method',
        'metadata',
        'confirmation_url',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_method' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSucceeded($query)
    {
        return $query->where('status', 'succeeded');
    }

    public function scopeCanceled($query)
    {
        return $query->where('status', 'canceled');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('payment_type', $type);
    }

    /**
     * Methods
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);
    }

    public function markAsCanceled(): void
    {
        $this->update([
            'status' => 'canceled',
        ]);
    }

    public function markAsRefunded(): void
    {
        $this->update([
            'status' => 'refunded',
        ]);
    }

    public function getStatusLabel(): string
    {
        $labels = [
            'pending' => 'Ожидает оплаты',
            'waiting_for_capture' => 'Ожидает подтверждения',
            'succeeded' => 'Оплачен',
            'canceled' => 'Отменен',
            'refunded' => 'Возвращен',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColor(): string
    {
        $colors = [
            'pending' => 'yellow',
            'waiting_for_capture' => 'blue',
            'succeeded' => 'green',
            'canceled' => 'gray',
            'refunded' => 'red',
        ];

        return $colors[$this->status] ?? 'gray';
    }

    public function getPaymentTypeLabel(): string
    {
        $labels = [
            'deposit' => 'Пополнение баланса',
            'subscription' => 'Оплата подписки',
            'addon' => 'Дополнительная услуга',
        ];

        return $labels[$this->payment_type] ?? $this->payment_type;
    }

    /**
     * Получить URL для редиректа на страницу оплаты
     */
    public function getPaymentUrl(): ?string
    {
        return $this->confirmation_url;
    }

    /**
     * Обработать успешную оплату
     */
    public function processSuccessfulPayment(): void
    {
        if ($this->isSucceeded()) {
            return; // Уже обработан
        }

        $this->markAsPaid();

        // Пополняем баланс если это депозит
        if ($this->payment_type === 'deposit') {
            $this->organization->balance->deposit(
                $this->amount,
                'Пополнение через ' . $this->getPaymentMethodName()
            );
        }

        // Активируем подписку если это оплата подписки
        if ($this->payment_type === 'subscription' && $this->subscription) {
            $this->subscription->update(['status' => 'active']);
        }
    }

    /**
     * Получить название платежного метода
     */
    public function getPaymentMethodName(): string
    {
        if (!$this->payment_method) {
            return 'Неизвестный метод';
        }

        $type = $this->payment_method['type'] ?? null;
        
        $methods = [
            'bank_card' => 'Банковская карта',
            'yoo_money' => 'ЮMoney',
            'sberbank' => 'Сбербанк Онлайн',
            'qiwi' => 'QIWI Кошелек',
            'webmoney' => 'WebMoney',
            'cash' => 'Наличные',
            'mobile_balance' => 'Баланс телефона',
        ];

        return $methods[$type] ?? 'Другой метод';
    }

    /**
     * Статистика платежей
     */
    public static function getStatistics(Organization $organization, $startDate = null, $endDate = null): array
    {
        $query = self::where('organization_id', $organization->id);
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        return [
            'total_amount' => $query->clone()->succeeded()->sum('amount'),
            'pending_amount' => $query->clone()->pending()->sum('amount'),
            'total_payments' => $query->clone()->count(),
            'successful_payments' => $query->clone()->succeeded()->count(),
            'failed_payments' => $query->clone()->canceled()->count(),
            'average_payment' => $query->clone()->succeeded()->avg('amount'),
        ];
    }
}