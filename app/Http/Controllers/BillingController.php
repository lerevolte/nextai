<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Tariff;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Subscription;
use Illuminate\Http\Request;
use YooKassa\Client;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    protected $yookassa;

    public function __construct()
    {
        $this->yookassa = new Client();
        $this->yookassa->setAuth(
            config('services.yookassa.shop_id'),
            config('services.yookassa.secret_key')
        );
    }

    /**
     * Страница тарифов
     */
    public function tariffs()
    {
        $organization = auth()->user()->organization;
        $tariffs = Tariff::active()->ordered()->get();
        $currentSubscription = $organization->subscriptions()->active()->first();
        
        return view('billing.tariffs', compact('organization', 'tariffs', 'currentSubscription'));
    }

    /**
     * Страница баланса и платежей
     */
    public function balance()
    {
        $organization = auth()->user()->organization;
        $balance = $organization->balance;
        $transactions = $organization->transactions()
            ->latest()
            ->paginate(20);
        $payments = $organization->payments()
            ->latest()
            ->paginate(10);
        
        return view('billing.balance', compact('organization', 'balance', 'transactions', 'payments'));
    }

    /**
     * Форма пополнения баланса
     */
    public function deposit()
    {
        $organization = auth()->user()->organization;
        $suggestedAmounts = [1000, 3000, 5000, 10000];
        
        return view('billing.deposit', compact('organization', 'suggestedAmounts'));
    }

    /**
     * Создание платежа для пополнения
     */
    public function createPayment(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100|max:100000',
            'return_url' => 'nullable|url',
        ]);

        $organization = auth()->user()->organization;
        
        try {
            // Создаем платеж в ЮКассе
            $idempotenceKey = Str::uuid()->toString();
            $payment = $this->yookassa->createPayment(
                [
                    'amount' => [
                        'value' => $validated['amount'],
                        'currency' => 'RUB',
                    ],
                    'confirmation' => [
                        'type' => 'redirect',
                        'return_url' => $validated['return_url'] ?? route('billing.payment.success'),
                    ],
                    'capture' => true,
                    'description' => "Пополнение баланса организации {$organization->name}",
                    'metadata' => [
                        'organization_id' => $organization->id,
                        'type' => 'deposit',
                    ],
                ],
                $idempotenceKey
            );

            // Сохраняем платеж в БД
            Payment::create([
                'organization_id' => $organization->id,
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'amount' => $validated['amount'],
                'currency' => 'RUB',
                'description' => $payment->description,
                'payment_type' => 'deposit',
                'confirmation_url' => $payment->confirmation->confirmation_url,
                'metadata' => [
                    'idempotence_key' => $idempotenceKey,
                ],
            ]);

            return redirect($payment->confirmation->confirmation_url);
            
        } catch (\Exception $e) {
            return back()->with('error', 'Ошибка создания платежа: ' . $e->getMessage());
        }
    }

    /**
     * Подписка на тарифный план
     */
    public function subscribe(Request $request, Tariff $tariff)
    {
        $validated = $request->validate([
            'period' => 'required|in:monthly,yearly',
        ]);

        $organization = auth()->user()->organization;
        
        // Проверяем баланс
        $price = $validated['period'] === 'yearly' ? $tariff->price_yearly : $tariff->price;
        
        if ($organization->balance->balance < $price) {
            return redirect()->route('billing.deposit')
                ->with('error', 'Недостаточно средств на балансе. Необходимо: ' . number_format($price, 2) . ' ₽');
        }

        // Отменяем текущую подписку если есть
        $currentSubscription = $organization->subscriptions()->active()->first();
        if ($currentSubscription) {
            $currentSubscription->cancel();
        }

        // Создаем новую подписку
        $subscription = Subscription::create([
            'organization_id' => $organization->id,
            'tariff_id' => $tariff->id,
            'status' => 'active',
            'billing_period' => $validated['period'],
            'started_at' => now(),
            'ends_at' => $validated['period'] === 'yearly' 
                ? now()->addYear() 
                : now()->addMonth(),
            'price' => $price,
        ]);

        // Списываем с баланса
        $organization->balance->withdraw($price, 'Подписка на тариф ' . $tariff->name);

        // Обновляем организацию
        $organization->update([
            'current_tariff_id' => $tariff->id,
            'is_trial' => false,
            'billing_period' => $validated['period'],
            'next_billing_date' => $subscription->ends_at,
            'bots_limit' => $tariff->bots_limit,
            'messages_limit' => $tariff->messages_limit,
        ]);

        return redirect()->route('billing.tariffs')
            ->with('success', 'Тариф успешно изменен!');
    }

    /**
     * Отмена подписки
     */
    public function cancelSubscription(Request $request)
    {
        $organization = auth()->user()->organization;
        $subscription = $organization->subscriptions()->active()->first();
        
        if (!$subscription) {
            return back()->with('error', 'У вас нет активной подписки');
        }

        $subscription->cancel();

        return redirect()->route('billing.tariffs')
            ->with('success', 'Подписка будет отменена в конце текущего периода');
    }

    /**
     * Webhook от ЮКассы
     */
    public function webhook(Request $request)
    {
        $notification = json_decode($request->getContent(), true);
        
        if ($notification['event'] === 'payment.succeeded') {
            $payment = Payment::where('payment_id', $notification['object']['id'])->first();
            
            if ($payment) {
                $payment->update([
                    'status' => 'succeeded',
                    'paid_at' => now(),
                    'payment_method' => $notification['object']['payment_method'] ?? null,
                ]);

                // Если это пополнение баланса
                if ($payment->payment_type === 'deposit') {
                    $organization = $payment->organization;
                    $organization->balance->deposit(
                        $payment->amount,
                        'Пополнение баланса через ЮКассу'
                    );
                }
            }
        }

        return response('', 200);
    }

    /**
     * Страница успешной оплаты
     */
    public function paymentSuccess(Request $request)
    {
        return view('billing.payment-success');
    }

    /**
     * История платежей
     */
    public function payments()
    {
        $organization = auth()->user()->organization;
        $payments = $organization->payments()
            ->with('subscription')
            ->latest()
            ->paginate(20);

        return view('billing.payments', compact('organization', 'payments'));
    }
}