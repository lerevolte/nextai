<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'tariff_id',
        'status',
        'billing_period',
        'started_at',
        'ends_at',
        'cancelled_at',
        'price',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
            ->orWhere(function ($q) {
                $q->where('status', 'active')
                  ->where('ends_at', '<', now());
            });
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Methods
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at > now();
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || 
               ($this->status === 'active' && $this->ends_at < now());
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function renew(): void
    {
        if ($this->billing_period === 'yearly') {
            $this->ends_at = $this->ends_at->addYear();
        } else {
            $this->ends_at = $this->ends_at->addMonth();
        }
        
        $this->status = 'active';
        $this->save();
    }

    public function getDaysRemaining(): int
    {
        if (!$this->isActive()) {
            return 0;
        }
        
        return now()->diffInDays($this->ends_at);
    }

    public function getProgressPercentage(): float
    {
        if (!$this->isActive()) {
            return 100;
        }
        
        $total = $this->started_at->diffInDays($this->ends_at);
        $passed = $this->started_at->diffInDays(now());
        
        return min(100, ($passed / $total) * 100);
    }
}