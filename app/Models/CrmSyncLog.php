<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'crm_integration_id',
        'direction',
        'entity_type',
        'action',
        'request_data',
        'response_data',
        'status',
        'error_message',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
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
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'error');
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', 'outgoing');
    }

    public function scopeByEntityType($query, string $type)
    {
        return $query->where('entity_type', $type);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Methods
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'error';
    }

    public function isIncoming(): bool
    {
        return $this->direction === 'incoming';
    }

    public function isOutgoing(): bool
    {
        return $this->direction === 'outgoing';
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'success' => 'green',
            'error' => 'red',
            'warning' => 'yellow',
            default => 'gray',
        };
    }

    public function getStatusIcon(): string
    {
        return match($this->status) {
            'success' => '✓',
            'error' => '✗',
            'warning' => '⚠',
            default => '•',
        };
    }

    public function getDirectionIcon(): string
    {
        return match($this->direction) {
            'incoming' => '←',
            'outgoing' => '→',
            default => '↔',
        };
    }

    public function getActionName(): string
    {
        $actions = [
            'create' => 'Создание',
            'update' => 'Обновление',
            'delete' => 'Удаление',
            'sync' => 'Синхронизация',
            'webhook' => 'Webhook',
            'fetch' => 'Получение',
            'search' => 'Поиск',
        ];

        return $actions[$this->action] ?? $this->action;
    }

    public function getEntityTypeName(): string
    {
        $types = [
            'lead' => 'Лид',
            'deal' => 'Сделка',
            'contact' => 'Контакт',
            'company' => 'Компания',
            'task' => 'Задача',
            'conversation' => 'Диалог',
            'message' => 'Сообщение',
            'webhook' => 'Webhook',
        ];

        return $types[$this->entity_type] ?? $this->entity_type;
    }

    /**
     * Создать лог для успешной операции
     */
    public static function logSuccess(
        CrmIntegration $integration,
        string $direction,
        string $entityType,
        string $action,
        array $requestData = [],
        array $responseData = []
    ): self {
        return self::create([
            'crm_integration_id' => $integration->id,
            'direction' => $direction,
            'entity_type' => $entityType,
            'action' => $action,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'status' => 'success',
        ]);
    }

    /**
     * Создать лог для неудачной операции
     */
    public static function logError(
        CrmIntegration $integration,
        string $direction,
        string $entityType,
        string $action,
        string $errorMessage,
        array $requestData = [],
        array $responseData = []
    ): self {
        return self::create([
            'crm_integration_id' => $integration->id,
            'direction' => $direction,
            'entity_type' => $entityType,
            'action' => $action,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'status' => 'error',
            'error_message' => $errorMessage,
        ]);
    }
}