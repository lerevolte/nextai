<?
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CachedResponse extends Model
{
    protected $fillable = [
        'bot_id',
        'question_hash',
        'question',
        'response',
        'hit_count',
        'last_used_at',
        'expires_at'
    ];

    protected $casts = [
        'hit_count' => 'integer',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function recordHit(): void
    {
        $this->increment('hit_count');
        $this->update(['last_used_at' => now()]);
    }

    public static function findByQuestion(Bot $bot, string $question): ?self
    {
        $hash = md5($question);
        
        return self::where('bot_id', $bot->id)
            ->where('question_hash', $hash)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public static function cacheResponse(Bot $bot, string $question, string $response, int $ttl = 86400): self
    {
        return self::updateOrCreate(
            [
                'bot_id' => $bot->id,
                'question_hash' => md5($question)
            ],
            [
                'question' => $question,
                'response' => $response,
                'expires_at' => now()->addSeconds($ttl),
                'hit_count' => 0
            ]
        );
    }
}