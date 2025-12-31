<?php

namespace App\Services\Providers;

use App\Services\AIProviderInterface;
use GuzzleHttp\Client;

class DeepSeekProvider implements AIProviderInterface
{
    protected Client $client;
    protected string $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = config('chatbot.ai_providers.deepseek.api_key');
    }

    public function generateResponse(
        string $model,
        array $messages,
        float $temperature,
        int $maxTokens
    ): string {
        $response = $this->client->post('https://api.deepseek.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        
        return $data['choices'][0]['message']['content'] ?? 'Извините, не удалось сгенерировать ответ.';
    }

    public function countTokens(string $text): int
    {
        return (int) (strlen($text) / 4);
    }
}