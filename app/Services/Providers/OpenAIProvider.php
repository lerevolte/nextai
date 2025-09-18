<?php

namespace App\Services\Providers;

use App\Services\AIProviderInterface;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements AIProviderInterface
{
    public function generateResponse(
        string $model,
        array $messages,
        float $temperature,
        int $maxTokens
    ): string {
        $response = OpenAI::chat()->create([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ]);

        return $response->choices[0]->message->content;
    }

    public function countTokens(string $text): int
    {
        // Приблизительный подсчет токенов
        return (int) (strlen($text) / 4);
    }
}