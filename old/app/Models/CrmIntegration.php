<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class CrmIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'type',
        'name',
        'credentials',
        'settings',
        'field_mapping',
        'is_active',
        'last_sync_at',
        'sync_status',
    ];

    protected $casts = [
        'settings' => 'array',
        'field_mapping' => 'array',
        'sync_status' => 'array',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'credentials',
    ];

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function bots(): BelongsToMany
    {
        return $this->belongsToMany(Bot::class, 'bot_crm_integrations')
            ->withPivot([
                'settings',
                'sync_contacts',
                'sync_conversations',
                'create_leads',
                'create_deals',
                'lead_source',
                'responsible_user_id',
                'pipeline_settings',
                'connector_settings',
                'is_active'
            ])
            ->withTimestamps();
    }

    public function syncEntities(): HasMany
    {
        return $this->hasMany(CrmSyncEntity::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(CrmSyncLog::class);
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
    public function getTypeName(): string
    {
        $types = [
            'bitrix24' => 'Битрикс24',
            'amocrm' => 'AmoCRM',
            'avito' => 'Avito',
        ];

        return $types[$this->type] ?? $this->type;
    }

    public function getIcon(): string
    {
        $icons = [
            'bitrix24' => '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="12" cy="12" r="12" fill="#3ac8f5"/>
                  <image 
                    x="4" 
                    y="4" 
                    width="16" 
                    height="16" 
                    href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 42 42%22%3E%3Cpath fill=%22%23FFF%22 d=%22M22.09 17.926h-1.386v3.716h3.551v-1.386H22.09v-2.33zm-.616 7.356a4.718 4.718 0 1 1 0-9.436 4.718 4.718 0 0 1 0 9.436zm9.195-6A5.19 5.19 0 0 0 23.721 14a5.19 5.19 0 0 0-9.872 1.69A6.234 6.234 0 0 0 15.233 28h14.761c2.444 0 4.425-1.724 4.425-4.425 0-3.497-3.406-4.379-3.75-4.293z%22/%3E%3C/svg%3E"
                  />
                </svg>',
            'amocrm' => '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="12" cy="12" r="12" fill="#3ac8f5"/>
                  <image 
                    x="4" 
                    y="4" 
                    width="16" 
                    height="16" 
                    href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 42 42%22%3E%3Cpath fill=%22%23FFF%22 d=%22M22.09 17.926h-1.386v3.716h3.551v-1.386H22.09v-2.33zm-.616 7.356a4.718 4.718 0 1 1 0-9.436 4.718 4.718 0 0 1 0 9.436zm9.195-6A5.19 5.19 0 0 0 23.721 14a5.19 5.19 0 0 0-9.872 1.69A6.234 6.234 0 0 0 15.233 28h14.761c2.444 0 4.425-1.724 4.425-4.425 0-3.497-3.406-4.379-3.75-4.293z%22/%3E%3C/svg%3E"
                  />
                </svg>',
            'avito' => '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="12" cy="12" r="12" fill="#3ac8f5"/>
                  <image 
                    x="4" 
                    y="4" 
                    width="16" 
                    height="16" 
                    href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 42 42%22%3E%3Cpath fill=%22%23FFF%22 d=%22M22.09 17.926h-1.386v3.716h3.551v-1.386H22.09v-2.33zm-.616 7.356a4.718 4.718 0 1 1 0-9.436 4.718 4.718 0 0 1 0 9.436zm9.195-6A5.19 5.19 0 0 0 23.721 14a5.19 5.19 0 0 0-9.872 1.69A6.234 6.234 0 0 0 15.233 28h14.761c2.444 0 4.425-1.724 4.425-4.425 0-3.497-3.406-4.379-3.75-4.293z%22/%3E%3C/svg%3E"
                  />
                </svg>',
        ];

        return $icons[$this->type] ?? '🔗';
    }

    public function isConfigured(): bool
    {
        return !empty($this->credentials);
    }

    public function getSyncEntity(string $entityType, string $localId): ?CrmSyncEntity
    {
        return $this->syncEntities()
            ->where('entity_type', $entityType)
            ->where('local_id', $localId)
            ->first();
    }

    public function createSyncEntity(string $entityType, string $localId, string $remoteId, array $remoteData = []): CrmSyncEntity
    {
        return $this->syncEntities()->create([
            'entity_type' => $entityType,
            'local_id' => $localId,
            'remote_id' => $remoteId,
            'remote_data' => $remoteData,
            'last_synced_at' => now(),
        ]);
    }

    public function logSync(string $direction, string $entityType, string $action, array $requestData = [], array $responseData = [], string $status = 'success', ?string $errorMessage = null): CrmSyncLog
    {
        return $this->syncLogs()->create([
            'direction' => $direction,
            'entity_type' => $entityType,
            'action' => $action,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    
}