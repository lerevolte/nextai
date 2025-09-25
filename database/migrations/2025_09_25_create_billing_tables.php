<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Таблица тарифов
        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2); // Цена в месяц
            $table->decimal('price_yearly', 10, 2)->nullable(); // Цена за год (со скидкой)
            
            // Лимиты
            $table->integer('bots_limit')->default(1);
            $table->integer('messages_limit')->default(1000); // В месяц
            $table->integer('users_limit')->default(1);
            $table->integer('knowledge_items_limit')->default(10);
            $table->boolean('crm_integration')->default(false);
            $table->boolean('api_access')->default(false);
            $table->boolean('white_label')->default(false);
            $table->boolean('priority_support')->default(false);
            
            // Дополнительные фичи
            $table->json('features')->nullable();
            
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->timestamps();
            
            $table->index('slug');
            $table->index('is_active');
        });
        
        // Подписки организаций
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('tariff_id')->constrained();
            $table->enum('status', ['active', 'cancelled', 'expired', 'suspended'])->default('active');
            $table->enum('billing_period', ['monthly', 'yearly'])->default('monthly');
            $table->timestamp('started_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->decimal('price', 10, 2); // Цена на момент подписки
            $table->timestamps();
            
            $table->index(['organization_id', 'status']);
            $table->index('ends_at');
        });
        
        // Баланс организации
        Schema::create('organization_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->decimal('balance', 10, 2)->default(0);
            $table->decimal('bonus_balance', 10, 2)->default(0); // Бонусный баланс
            $table->decimal('hold_amount', 10, 2)->default(0); // Заблокированная сумма
            $table->timestamps();
            
            $table->unique('organization_id');
        });
        
        // Транзакции
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['deposit', 'withdrawal', 'subscription', 'refund', 'bonus']);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['organization_id', 'type']);
            $table->index('created_at');
        });
        
        // Платежи через ЮКассу
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('payment_id')->unique(); // ID платежа в ЮКассе
            $table->enum('status', ['pending', 'waiting_for_capture', 'succeeded', 'canceled', 'refunded']);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('RUB');
            $table->string('description');
            $table->enum('payment_type', ['deposit', 'subscription', 'addon']);
            $table->foreignId('subscription_id')->nullable()->constrained();
            $table->json('payment_method')->nullable();
            $table->json('metadata')->nullable();
            $table->string('confirmation_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->index(['organization_id', 'status']);
            $table->index('payment_id');
        });
        
        // Счета на оплату
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->enum('status', ['draft', 'sent', 'paid', 'cancelled']);
            $table->decimal('amount', 10, 2);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->text('items'); // JSON с позициями счета
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->index(['organization_id', 'status']);
            $table->index('invoice_number');
        });
        
        // Добавляем поля в таблицу organizations
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('current_tariff_id')->nullable()->after('is_active')->constrained('tariffs');
            $table->boolean('is_trial')->default(true)->after('current_tariff_id');
            $table->timestamp('trial_ends_at')->nullable()->after('is_trial');
            $table->enum('billing_period', ['monthly', 'yearly'])->default('monthly')->after('trial_ends_at');
            $table->timestamp('next_billing_date')->nullable()->after('billing_period');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['current_tariff_id']);
            $table->dropColumn(['current_tariff_id', 'is_trial', 'trial_ends_at', 'billing_period', 'next_billing_date']);
        });
        
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('organization_balances');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tariffs');
    }
};