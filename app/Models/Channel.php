<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'type',
        'name',
        'credentials',
        'settings',
        'is_active',
        'last_sync_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'credentials',
    ];

    /**
     * Relationships
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Accessors & Mutators
     */
    public function getCredentialsAttribute($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decrypt($value);
        } catch (\Exception $e) {
            return json_decode($value, true);
        }
    }

    public function setCredentialsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['credentials'] = Crypt::encrypt($value);
        } else {
            $this->attributes['credentials'] = $value;
        }
    }

    /**
     * Methods
     */
    public function getWebhookUrl(): string
    {
        return route('webhooks.' . $this->type, $this);
    }

    public function isConfigured(): bool
    {
        return !empty($this->credentials);
    }

    public function getTypeName(): string
    {
        $types = [
            'web' => 'Веб-виджет',
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp',
            'vk' => 'ВКонтакте',
            'instagram' => 'Instagram',
            'avito' => 'Avito',
            'bitrix24' => 'Битрикс24',
            'amocrm' => 'AmoCRM',
        ];

        return $types[$this->type] ?? $this->type;
    }

    public function getIcon(): string
    {
        $icons = [
            'web' => '🌐',
            'telegram' => '✈️',
            'whatsapp' => '💬',
            'vk' => '📱',
            'instagram' => '📷',
        ];

        return $icons[$this->type] ?? '📡';
    }
}