<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        // Статистика за последние 30 дней
        $startDate = Carbon::now()->subDays(30);

        $stats = [
            'total_bots' => $organization->bots()->count(),
            'active_bots' => $organization->bots()->where('is_active', true)->count(),
            'total_conversations' => $organization->bots()
                ->join('conversations', 'bots.id', '=', 'conversations.bot_id')
                ->where('conversations.created_at', '>=', $startDate)
                ->count('conversations.id'),
            'active_conversations' => $organization->bots()
                ->join('conversations', 'bots.id', '=', 'conversations.bot_id')
                ->where('conversations.status', 'active')
                ->count('conversations.id'),
            'messages_sent' => $organization->bots()
                ->join('conversations', 'bots.id', '=', 'conversations.bot_id')
                ->join('messages', 'conversations.id', '=', 'messages.conversation_id')
                ->where('messages.created_at', '>=', $startDate)
                ->count('messages.id'),
            'unique_users' => $organization->bots()
                ->join('conversations', 'bots.id', '=', 'conversations.bot_id')
                ->where('conversations.created_at', '>=', $startDate)
                ->distinct('conversations.user_email')
                ->count('conversations.user_email'),
        ];

        // График сообщений за последние 7 дней
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = $organization->bots()
                ->join('conversations', 'bots.id', '=', 'conversations.bot_id')
                ->join('messages', 'conversations.id', '=', 'messages.conversation_id')
                ->whereDate('messages.created_at', $date)
                ->count('messages.id');
            
            $chartData[] = [
                'date' => $date->format('d.m'),
                'count' => $count,
            ];
        }

        // Последние диалоги
        $recentConversations = $organization->bots()
            ->join('conversations', 'bots.id', '=', 'conversations.bot_id')
            ->join('channels', 'channels.id', '=', 'conversations.channel_id')
            ->select('conversations.*', 'bots.name as bot_name', 'channels.type as channel_type')
            ->orderBy('conversations.created_at', 'desc')
            ->take(10)
            ->get();

        // Популярные боты
        $topBots = $organization->bots()
            ->withCount(['conversations' => function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }])
            ->orderBy('conversations_count', 'desc')
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'organization',
            'stats',
            'chartData',
            'recentConversations',
            'topBots'
        ));
    }
}