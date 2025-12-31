<?php

namespace App\Services\CRM;

use App\Models\Conversation;
use App\Models\CrmIntegration;

interface CrmProviderInterface
{
    /**
     * Проверка соединения с CRM
     */
    public function testConnection(): bool;

    /**
     * Создание или обновление контакта
     */
    public function syncContact(array $contactData): array;

    /**
     * Создание лида
     */
    public function createLead(Conversation $conversation, array $additionalData = []): array;

    /**
     * Обновление лида
     */
    public function updateLead(string $leadId, array $data): array;

    /**
     * Создание сделки
     */
    public function createDeal(Conversation $conversation, array $additionalData = []): array;

    /**
     * Обновление сделки
     */
    public function updateDeal(string $dealId, array $data): array;

    /**
     * Добавление примечания/комментария
     */
    public function addNote(string $entityType, string $entityId, string $note): bool;

    /**
     * Получение списка пользователей CRM
     */
    public function getUsers(): array;

    /**
     * Получение списка воронок/пайплайнов
     */
    public function getPipelines(): array;

    /**
     * Получение списка этапов воронки
     */
    public function getPipelineStages(string $pipelineId): array;

    /**
     * Получение информации о сущности
     */
    public function getEntity(string $entityType, string $entityId): ?array;

    /**
     * Поиск контакта по email или телефону
     */
    public function findContact(string $email = null, string $phone = null): ?array;

    /**
     * Синхронизация диалога с CRM
     */
    public function syncConversation(Conversation $conversation): bool;

    /**
     * Обработка webhook от CRM
     */
    public function handleWebhook(array $data): void;

    /**
     * Получение настроек полей CRM
     */
    public function getFields(string $entityType): array;

    /**
     * Массовая синхронизация
     */
    public function bulkSync(array $entities): array;
}