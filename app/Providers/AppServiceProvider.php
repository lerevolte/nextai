<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\KnowledgeItem;
use App\Observers\KnowledgeItemObserver;

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
    }
}
