<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBotMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Conversation $conversation;
    protected string $userMessage;
    protected ?string $callbackUrl;

    public function __construct(Conversation $conversation, string $userMessage, ?string $callbackUrl = null)
    {
        $this->conversation = $conversation;
        $this->userMessage = $userMessage;
        $this->callbackUrl = $callbackUrl;
    }

    public function handle(AIService $aiService)
    {
        try {
            $bot = $this->conversation->bot;
            
            // Генерируем ответ
            $startTime = microtime(true);
            $responseContent = $aiService->generateResponse(
                $bot,
                $this->conversation,
                $this->userMessage
            );
            $responseTime = microtime(true) - $startTime;

            // Сохраняем ответ
            $botMessage = $this->conversation->messages()->create([
                'role' => 'assistant',
                'content' => $responseContent,
                'response_time' => $responseTime,
            ]);

            // Обновляем счетчики
            $this->conversation->increment('messages_count');
            $this->conversation->update(['last_message_at' => now()]);

            // Если есть callback URL, отправляем результат
            if ($this->callbackUrl) {
                $this->sendCallback($botMessage);
            }

            // Broadcast событие для real-time обновления
            broadcast(new \App\Events\BotMessageSent($this->conversation, $botMessage));

        } catch (\Exception $e) {
            Log::error('Failed to process bot message', [
                'conversation_id' => $this->conversation->id,
                'error' => $e->getMessage(),
            ]);

            // Создаем сообщение об ошибке
            $errorMessage = $this->conversation->messages()->create([
                'role' => 'system',
                'content' => 'Извините, произошла ошибка при обработке вашего сообщения. Пожалуйста, попробуйте позже.',
            ]);

            if ($this->callbackUrl) {
                $this->sendCallback($errorMessage, true);
            }
        }
    }

    protected function sendCallback(Message $message, bool $isError = false)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $client->post($this->callbackUrl, [
                'json' => [
                    'conversation_id' => $this->conversation->id,
                    'message' => [
                        'id' => $message->id,
                        'role' => $message->role,
                        'content' => $message->content,
                        'created_at' => $message->created_at->toIso8601String(),
                    ],
                    'error' => $isError,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send callback', [
                'url' => $this->callbackUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}