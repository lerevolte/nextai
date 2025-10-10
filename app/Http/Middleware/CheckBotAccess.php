<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Bot;
use App\Models\Organization;

class CheckBotAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        
        if (!$user) {
            abort(403, 'Unauthorized');
        }
        
        // Получаем bot из параметров роута
        $botParam = $request->route('bot');
        
        // Если это уже модель Bot, используем её
        if ($botParam instanceof Bot) {
            $bot = $botParam;
        }
        // Если это ID, загружаем модель
        elseif (is_numeric($botParam) || is_string($botParam)) {
            $bot = Bot::find($botParam);
            
            if (!$bot) {
                abort(404, 'Bot not found');
            }
        } else {
            abort(404, 'Invalid bot parameter');
        }
        
        // Проверяем доступ
        if (!$user->canAccessBot($bot)) {
            abort(403, 'Access denied to this bot');
        }
        
        // Сохраняем бота в request для дальнейшего использования
        $request->merge(['bot_model' => $bot]);
        
        return $next($request);
    }
}