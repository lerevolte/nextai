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
            'telegram' => '<svg class="w-6 h-6 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"></path>
                        </svg>',
            'whatsapp' => '<svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.149-.67.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.123-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>',
            'vk' => '<svg class="w-6 h-6" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                      <circle cx="12" cy="12" r="12" fill="#0077FF"></circle>
                      <path d="M12.77 18.274c-5.47 0-8.59-3.75-8.72-9.99h2.74c.09 4.58 2.11 6.52 3.71 6.92v-6.92h2.58v3.95c1.58-.17 3.24-1.97 3.8-3.95h2.58c-.43 2.44-2.23 4.24-3.51 4.98 1.28.6 3.33 2.17 4.11 5.01h-2.84c-.61-1.9-2.13-3.37-4.14-3.57v3.57h-.31Z" fill="#fff"></path>
                    </svg>',
            'instagram' => '<svg class="w-6 h-6" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                      <defs>
                        <radialGradient id="ig-gradient" cx="30%" cy="107%" r="150%">
                          <stop stop-color="#fdf497" offset="0%"/>
                          <stop stop-color="#fdf497" offset="5%"/>
                          <stop stop-color="#fd5949" offset="45%"/>
                          <stop stop-color="#d6249f" offset="60%"/>
                          <stop stop-color="#285AEB" offset="90%"/>
                        </radialGradient>
                      </defs>
                      <circle cx="12" cy="12" r="12" fill="url(#ig-gradient)"/>
                      <g fill="none" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="6.2" y="6.2" width="11.6" height="11.6" rx="3"/>
                        <circle cx="12" cy="12" r="2.9"/>
                        <circle cx="15.2" cy="8.8" r="0.9"/>
                      </g>
                    </svg>',
        ];

        return $icons[$this->type] ?? '📡';
    }
}