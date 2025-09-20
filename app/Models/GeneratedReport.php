<?
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedReport extends Model
{
    protected $fillable = [
        'organization_id',
        'scheduled_report_id',
        'name',
        'format',
        'file_path',
        'file_size',
        'parameters',
        'metrics_snapshot',
        'generated_at',
        'expires_at'
    ];

    protected $casts = [
        'parameters' => 'array',
        'metrics_snapshot' => 'array',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scheduledReport(): BelongsTo
    {
        return $this->belongsTo(ScheduledReport::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getDownloadUrl(): string
    {
        return route('reports.download', $this);
    }
}