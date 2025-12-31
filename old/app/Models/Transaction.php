<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scopes
     */
    public function scopeDeposits($query)
    {
        return $query->where('type', 'deposit');
    }

    public function scopeWithdrawals($query)
    {
        return $query->where('type', 'withdrawal');
    }

    public function scopeSubscriptions($query)
    {
        return $query->where('type', 'subscription');
    }

    public function scopeRefunds($query)
    {
        return $query->where('type', 'refund');
    }

    public function scopeBonuses($query)
    {
        return $query->where('type', 'bonus');
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Методы
     */
    public function isDeposit(): bool
    {
        return $this->type === 'deposit';
    }

    public function isWithdrawal(): bool
    {
        return $this->type === 'withdrawal' || $this->type === 'subscription';
    }

    public function isRefund(): bool
    {
        return $this->type === 'refund';
    }

    public function isBonus(): bool
    {
        return $this->type === 'bonus';
    }

    public function getTypeLabel(): string
    {
        $labels = [
            'deposit' => 'Пополнение',
            'withdrawal' => 'Списание',
            'subscription' => 'Подписка',
            'refund' => 'Возврат',
            'bonus' => 'Бонус',
        ];

        return $labels[$this->type] ?? $this->type;
    }

    public function getTypeIcon(): string
    {
        $icons = [
            'deposit' => '💳',
            'withdrawal' => '💸',
            'subscription' => '📅',
            'refund' => '↩️',
            'bonus' => '🎁',
        ];

        return $icons[$this->type] ?? '💰';
    }

    public function getAmountFormatted(): string
    {
        $sign = $this->amount >= 0 ? '+' : '';
        return $sign . number_format($this->amount, 2) . ' ₽';
    }

    /**
     * Получить статистику транзакций за период
     */
    public static function getStatistics(Organization $organization, $startDate = null, $endDate = null): array
    {
        $query = self::where('organization_id', $organization->id);
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        return [
            'total_deposits' => $query->clone()->deposits()->sum('amount'),
            'total_withdrawals' => abs($query->clone()->withdrawals()->sum('amount')),
            'total_bonuses' => $query->clone()->bonuses()->sum('amount'),
            'total_refunds' => $query->clone()->refunds()->sum('amount'),
            'transactions_count' => $query->clone()->count(),
        ];
    }
}