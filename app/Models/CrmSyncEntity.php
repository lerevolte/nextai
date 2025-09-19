<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmSyncEntity extends Model
{
    use HasFactory;

    protected $fillable = [
        'crm_integration_id',
        'entity_type',
        'local_id',
        'remote_id',
        'remote_data',
        'last_synced_at',
        'sync_metadata',
    ];

    protected $casts = [
        'remote_data' => 'array',
        'sync_metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(CrmIntegration::class, 'crm_integration_id');
    }

    /**
     * Scopes
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('entity_type', $type);
    }

    public function scopeByLocalId($query, string $localId)
    {
        return $query->where('local_id', $localId);
    }

    public function scopeByRemoteId($query, string $remoteId)
    {
        return $query->where('remote_id', $remoteId);
    }

    /**
     * Methods
     */
    public function getLocalEntity()
    {
        return match($this->entity_type) {
            'conversation' => Conversation::find($this->local_id),
            'lead' => Conversation::find($this->local_id), // Лиды связаны с диалогами
            'deal' => Conversation::find($this->local_id), // Сделки связаны с диалогами
            'contact' => null, // Контакты не имеют локальной сущности
            default => null,
        };
    }

    public function updateSyncStatus(array $remoteData = []): void
    {
        $this->update([
            'remote_data' => array_merge($this->remote_data ?? [], $remoteData),
            'last_synced_at' => now(),
            'sync_metadata' => array_merge($this->sync_metadata ?? [], [
                'last_sync_status' => 'success',
                'last_sync_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function markSyncFailed(string $error): void
    {
        $this->update([
            'sync_metadata' => array_merge($this->sync_metadata ?? [], [
                'last_sync_status' => 'failed',
                'last_sync_error' => $error,
                'last_sync_attempt_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function getRemoteUrl(): ?string
    {
        $integration = $this->integration;
        
        if (!$integration) {
            return null;
        }

        return match($integration->type) {
            'bitrix24' => $this->getBitrix24Url(),
            'amocrm' => $this->getAmoCRMUrl(),
            'avito' => null, // Avito не имеет прямых ссылок
            default => null,
        };
    }

    protected function getBitrix24Url(): ?string
    {
        $config = $this->integration->credentials;
        $webhookUrl = $config['webhook_url'] ?? '';
        
        // Извлекаем домен из webhook URL
        if (preg_match('/https:\/\/([^\/]+)/', $webhookUrl, $matches)) {
            $domain = $matches[1];
            
            return match($this->entity_type) {
                'lead' => "https://{$domain}/crm/lead/details/{$this->remote_id}/",
                'deal' => "https://{$domain}/crm/deal/details/{$this->remote_id}/",
                'contact' => "https://{$domain}/crm/contact/details/{$this->remote_id}/",
                'company' => "https://{$domain}/crm/company/details/{$this->remote_id}/",
                default => null,
            };
        }
        
        return null;
    }

    protected function getAmoCRMUrl(): ?string
    {
        $config = $this->integration->credentials;
        $subdomain = $config['subdomain'] ?? '';
        
        if (!$subdomain) {
            return null;
        }

        return match($this->entity_type) {
            'lead' => "https://{$subdomain}.amocrm.ru/leads/detail/{$this->remote_id}",
            'contact' => "https://{$subdomain}.amocrm.ru/contacts/detail/{$this->remote_id}",
            'company' => "https://{$subdomain}.amocrm.ru/companies/detail/{$this->remote_id}",
            default => null,
        };
    }
}