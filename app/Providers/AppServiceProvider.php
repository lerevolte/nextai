<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\KnowledgeItem;
use App\Models\Conversation;
use App\Observers\KnowledgeItemObserver;
use App\Observers\ConversationObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        KnowledgeItem::observe(KnowledgeItemObserver::class);
        Conversation::observe(ConversationObserver::class);
    }
}

