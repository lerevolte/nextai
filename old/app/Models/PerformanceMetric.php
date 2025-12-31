<?
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceMetric extends Model
{
    protected $fillable = [
        'metric_type',
        'metric_name',
        'value',
        'metadata',
        'recorded_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'recorded_at' => 'datetime',
        'value' => 'float'
    ];

    public function scopeByType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('recorded_at', '>=', now()->subHours($hours));
    }

    public static function record(string $type, string $name, float $value, array $metadata = []): self
    {
        return self::create([
            'metric_type' => $type,
            'metric_name' => $name,
            'value' => $value,
            'metadata' => $metadata,
            'recorded_at' => now()
        ]);
    }
}