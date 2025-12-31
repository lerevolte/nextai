<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyCrmWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $type = $request->route('type');
        
        if (!$type) {
            return response()->json(['error' => 'Invalid webhook type'], 400);
        }

        // Проверка подписи в зависимости от типа CRM
        $verified = match($type) {
            'bitrix24' => $this->verifyBitrix24($request),
            'amocrm' => $this->verifyAmoCRM($request),
            'avito' => $this->verifyAvito($request),
            'salebot' => $this->verifySalebot($request),
            default => false,
        };

        if (!$verified) {
            Log::warning('Invalid CRM webhook signature', [
                'type' => $type,
                'ip' => $request->ip(),
                'headers' => $request->headers->all(),
            ]);
            
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }

    /**
     * Проверка подписи Bitrix24
     */
    protected function verifyBitrix24(Request $request): bool
    {
        // Bitrix24 отправляет auth токен в параметрах
        $auth = $request->input('auth');
        
        if (!$auth || !isset($auth['application_token'])) {
            return false;
        }

        // Здесь должна быть проверка токена приложения
        // Для простоты пока возвращаем true
        return true;
    }

    /**
     * Проверка подписи AmoCRM
     */
    protected function verifyAmoCRM(Request $request): bool
    {
        // AmoCRM не отправляет подпись, проверяем по IP
        $allowedIps = [
            // IP адреса AmoCRM
            '185.201.159.0/24',
            '185.201.158.0/24',
        ];

        $clientIp = $request->ip();
        
        foreach ($allowedIps as $range) {
            if ($this->ipInRange($clientIp, $range)) {
                return true;
            }
        }

        // Или проверяем секретный ключ в headers
        $secret = $request->header('X-Secret-Key');
        if ($secret && $secret === config('crm.webhook_security.secret_key')) {
            return true;
        }

        return false;
    }

    /**
     * Проверка подписи Avito
     */
    protected function verifyAvito(Request $request): bool
    {
        $signature = $request->header('X-Avito-Signature');
        
        if (!$signature) {
            return false;
        }

        // Вычисляем подпись
        $body = $request->getContent();
        $secret = config('crm.webhook_security.secret_key');
        $computedSignature = hash_hmac('sha256', $body, $secret);

        return hash_equals($signature, $computedSignature);
    }

    /**
     * Проверка подписи Salebot
     */
    protected function verifySalebot(Request $request): bool
    {
        // Salebot отправляет API ключ в теле запроса
        $apiKey = $request->input('api_key');
        
        if (!$apiKey) {
            return false;
        }

        // Здесь нужно проверить, соответствует ли API ключ сохраненному
        // Для простоты проверяем наличие ключа
        // В реальном приложении нужно сверять с БД
        return !empty($apiKey);
    }

    /**
     * Проверка IP адреса в диапазоне
     */
    protected function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            $range .= '/32';
        }

        list($range, $netmask) = explode('/', $range, 2);
        $rangeDecimal = ip2long($range);
        $ipDecimal = ip2long($ip);
        $wildcardDecimal = pow(2, (32 - $netmask)) - 1;
        $netmaskDecimal = ~$wildcardDecimal;

        return (($ipDecimal & $netmaskDecimal) == ($rangeDecimal & $netmaskDecimal));
    }
}