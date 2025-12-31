<?
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbTestResult extends Model
{
    protected $fillable = [
        'ab_test_id',
        'variant_id',
        'conversation_id',
        'metrics'
    ];

    protected $casts = [
        'metrics' => 'array'
    ];

    public function abTest(): BelongsTo
    {
        return $this->belongsTo(AbTest::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(AbTestVariant::class, 'variant_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}