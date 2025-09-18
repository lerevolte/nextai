<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckBotAccess
{
    public function handle(Request $request, Closure $next)
    {
        $bot = $request->route('bot');
        
        if (!$bot) {
            return $next($request);
        }

        if (!$request->user()->canAccessBot($bot)) {
            abort(403, 'У вас нет доступа к этому боту');
        }

        return $next($request);
    }
}