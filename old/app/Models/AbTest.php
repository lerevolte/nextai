<?
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbTest extends Model
{
    protected $fillable = [
        'organization_id',
        'bot_id',
        'name',
        'description',
        'type',
        'status',
        'traffic_percentage',
        'min_sample_size',
        'confidence_level',
        'starts_at',
        'ends_at',
        'completed_at',
        'winner_variant_id',
        'auto_apply_winner',
        'settings',
        'priority'
    ];

    protected $casts = [
        'settings' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'completed_at' => 'datetime',
        'auto_apply_winner' => 'boolean',
        'traffic_percentage' => 'integer',
        'min_sample_size' => 'integer',
        'confidence_level' => 'integer',
        'priority' => 'integer'
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(AbTestVariant::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(AbTestResult::class);
    }

    public function winnerVariant(): BelongsTo
    {
        return $this->belongsTo(AbTestVariant::class, 'winner_variant_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>', now());
            });
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}