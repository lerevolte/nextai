<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'price_yearly',
        'bots_limit',
        'messages_limit',
        'users_limit',
        'knowledge_items_limit',
        'crm_integration',
        'api_access',
        'white_label',
        'priority_support',
        'features',
        'sort_order',
        'is_active',
        'is_popular',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
        'crm_integration' => 'boolean',
        'api_access' => 'boolean',
        'white_label' => 'boolean',
        'priority_support' => 'boolean',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function organizations()
    {
        return $this->hasMany(Organization::class, 'current_tariff_id');
    }

    public function getMonthlyPriceAttribute()
    {
        return $this->price;
    }

    public function getYearlyPriceAttribute()
    {
        return $this->price_yearly ?? ($this->price * 12);
    }

    public function getYearlySavingsAttribute()
    {
        $fullPrice = $this->price * 12;
        $yearlyPrice = $this->price_yearly ?? $fullPrice;
        return $fullPrice - $yearlyPrice;
    }

    public function getYearlySavingsPercentAttribute()
    {
        if ($this->price == 0) return 0;
        $fullPrice = $this->price * 12;
        $yearlyPrice = $this->price_yearly ?? $fullPrice;
        return round((($fullPrice - $yearlyPrice) / $fullPrice) * 100);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    public static function getDefaultTariffs()
    {
        return [
            [
                'name' => 'Старт',
                'slug' => 'starter',
                'description' => 'Идеально для начала работы с чат-ботами',
                'price' => 0,
                'price_yearly' => 0,
                'bots_limit' => 1,
                'messages_limit' => 1000,
                'users_limit' => 1,
                'knowledge_items_limit' => 10,
                'crm_integration' => false,
                'api_access' => false,
                'white_label' => false,
                'priority_support' => false,
                'features' => [
                    'Веб-виджет',
                    'Базовая аналитика',
                    'Email поддержка',
                ],
                'sort_order' => 1,
                'is_popular' => false,
            ],
            [
                'name' => 'Базовый',
                'slug' => 'basic',
                'description' => 'Для малого бизнеса',
                'price' => 1,
                'price_yearly' => 10, // ~17% скидка
                'bots_limit' => 3,
                'messages_limit' => 10000,
                'users_limit' => 3,
                'knowledge_items_limit' => 100,
                'crm_integration' => true,
                'api_access' => false,
                'white_label' => false,
                'priority_support' => false,
                'features' => [
                    'Все из тарифа Старт',
                    'Telegram и WhatsApp',
                    '1 CRM интеграция',
                    'Экспорт данных',
                    'Чат поддержка',
                ],
                'sort_order' => 2,
                'is_popular' => true,
            ],
            [
                'name' => 'Профессионал',
                'slug' => 'professional',
                'description' => 'Для растущего бизнеса',
                'price' => 2,
                'price_yearly' => 2, // ~20% скидка
                'bots_limit' => 10,
                'messages_limit' => 50000,
                'users_limit' => 10,
                'knowledge_items_limit' => 500,
                'crm_integration' => true,
                'api_access' => true,
                'white_label' => false,
                'priority_support' => true,
                'features' => [
                    'Все из тарифа Базовый',
                    'Все каналы',
                    'Неограниченные CRM',
                    'API доступ',
                    'A/B тестирование',
                    'Приоритетная поддержка',
                    'Персональный менеджер',
                ],
                'sort_order' => 3,
                'is_popular' => false,
            ],
            [
                'name' => 'Корпоративный',
                'slug' => 'enterprise',
                'description' => 'Индивидуальные условия',
                'price' => 3,
                'price_yearly' => 3, // ~20% скидка
                'bots_limit' => -1, // Неограниченно
                'messages_limit' => -1,
                'users_limit' => -1,
                'knowledge_items_limit' => -1,
                'crm_integration' => true,
                'api_access' => true,
                'white_label' => true,
                'priority_support' => true,
                'features' => [
                    'Все возможности платформы',
                    //'White Label',
                    //'SLA 99.9%',
                    //'Выделенный сервер',
                    'Индивидуальные интеграции',
                    'Обучение команды',
                    'Техническая поддержка 24/7',
                ],
                'sort_order' => 4,
                'is_popular' => false,
            ],
        ];
    }
}