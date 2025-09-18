<?php

namespace App\Services;

interface AIProviderInterface
{
    /**
     * Генерирует ответ на основе сообщений
     *
     * @param string $model
     * @param array $messages
     * @param float $temperature
     * @param int $maxTokens
     * @return string
     */
    public function generateResponse(
        string $model,
        array $messages,
        float $temperature,
        int $maxTokens
    ): string;

    /**
     * Подсчитывает количество токенов в тексте
     *
     * @param string $text
     * @return int
     */
    public function countTokens(string $text): int;
}