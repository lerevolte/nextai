<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckConversationStatus
{
    public function handle(Request $request, Closure $next)
    {
        $conversationId = $request->route('conversation')?->id;
        
        if ($conversationId) {
            $conversation = \App\Models\Conversation::find($conversationId);
            
            if ($conversation && $conversation->status === 'waiting_operator') {
                \Log::info('Request blocked - operator is handling', [
                    'conversation_id' => $conversationId,
                    'route' => $request->route()->getName()
                ]);
                
                return response()->json([
                    'status' => 'operator_handling',
                    'message' => 'Оператор обрабатывает диалог'
                ], 423);
            }
        }
        
        return $next($request);
    }
}