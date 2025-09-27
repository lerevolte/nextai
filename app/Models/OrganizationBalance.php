<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class OrganizationBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'balance',
        'bonus_balance',
        'hold_amount',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'bonus_balance' => 'decimal:2',
        'hold_amount' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Methods
     */
    public function getTotalBalance(): float
    {
        return $this->balance + $this->bonus_balance;
    }

    public function getAvailableBalance(): float
    {
        return $this->balance + $this->bonus_balance - $this->hold_amount;
    }

    public function canWithdraw(float $amount): bool
    {
        return $this->getAvailableBalance() >= $amount;
    }

    /**
     * Пополнить баланс
     */
    public function deposit(float $amount, string $description = 'Пополнение баланса', array $metadata = []): Transaction
    {
        return DB::transaction(function () use ($amount, $description, $metadata) {
            $balanceBefore = $this->balance;
            
            $this->increment('balance', $amount);
            
            return Transaction::create([
                'organization_id' => $this->organization_id,
                'type' => 'deposit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore + $amount,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Списать с баланса
     */
    public function withdraw(float $amount, string $description = 'Списание', array $metadata = []): Transaction
    {
        if (!$this->canWithdraw($amount)) {
            throw new \Exception('Недостаточно средств на балансе');
        }

        return DB::transaction(function () use ($amount, $description, $metadata) {
            $balanceBefore = $this->balance;
            $amountToWithdraw = $amount;
            
            // Сначала используем бонусный баланс
            if ($this->bonus_balance > 0) {
                $bonusUsed = min($this->bonus_balance, $amountToWithdraw);
                $this->decrement('bonus_balance', $bonusUsed);
                $amountToWithdraw -= $bonusUsed;
            }
            
            // Остальное списываем с основного баланса
            if ($amountToWithdraw > 0) {
                $this->decrement('balance', $amountToWithdraw);
            }
            
            return Transaction::create([
                'organization_id' => $this->organization_id,
                'type' => 'withdrawal',
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->balance,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Добавить бонусы
     */
    public function addBonus(float $amount, string $description = 'Начисление бонусов'): Transaction
    {
        return DB::transaction(function () use ($amount, $description) {
            $this->increment('bonus_balance', $amount);
            
            return Transaction::create([
                'organization_id' => $this->organization_id,
                'type' => 'bonus',
                'amount' => $amount,
                'balance_before' => $this->balance,
                'balance_after' => $this->balance,
                'description' => $description,
                'metadata' => ['bonus_amount' => $amount],
            ]);
        });
    }

    /**
     * Заблокировать сумму
     */
    public function hold(float $amount): void
    {
        if (!$this->canWithdraw($amount)) {
            throw new \Exception('Недостаточно средств для блокировки');
        }
        
        $this->increment('hold_amount', $amount);
    }

    /**
     * Разблокировать сумму
     */
    public function release(float $amount): void
    {
        $this->decrement('hold_amount', min($amount, $this->hold_amount));
    }

    /**
     * Создать баланс для организации если его нет
     */
    public static function createForOrganization(Organization $organization): self
    {
        return self::firstOrCreate(
            ['organization_id' => $organization->id],
            [
                'balance' => 0,
                'bonus_balance' => 0,
                'hold_amount' => 0,
            ]
        );
    }
}