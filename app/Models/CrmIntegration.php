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
            'bitrix24' => '🏢',
            'amocrm' => '📊',
            'avito' => '🏪',
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