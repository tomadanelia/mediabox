<?php

namespace App\Services;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\SiteSetting;
use App\Models\Discount;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use \Illuminate\Support\Str;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Cache;
use App\Services\BroadcastService;
class SubscriptionService
{
     public function __construct(
        protected BroadcastService $broadcast 
    ) {}
    public function purchasePlan(User $user, string $planId,bool $autoRenew ): array
    {   
    $identifier = $user->full_name ?? (string)$user->numeric_id;
    $field = $user->full_name ? 'full_name' : 'customer_id';

    if ($user->company_id !== null) {
        $user->loadMissing('company');
        $identifier = $user->company->name ?? 'Unknown Company';
        $field = 'company_name';
    }
        return DB::transaction(function () use ($user, $planId,$identifier,$field,$autoRenew) {
            $account = $user->account()->lockForUpdate()->first();
            
            if (!$account) {
                throw ValidationException::withMessages(['account' => 'User account not found.']);
            }

            $plan = SubscriptionPlan::findOrFail($planId);
            $price = $this->getBestPrice($user, $plan->id, (float) $plan->price);
            if ($account->balance < $price) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient balance. Current balance: ' . $account->balance . ' GEL'
                ]);
            }

            $account->balance -= $price;
            $account->save();

            $transaction = PaymentTransaction::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'amount' => $price,
                'currency' => 'GEL',
                'status' => 'completed',
                'payment_method' => 'account_balance',
                'metadata' => ['previous_balance' => $account->balance + $price,'remaining_balance' => $account->balance]
            ]);

            
            $existingSub = $user->subscriptionPlans()
                                ->where('subscription_plans.id', $plan->id)
                                ->wherePivot('is_active', true)
                                ->wherePivot('expires_at', '>', now())
                                ->first();
            $startDate = now();
            $expiresAt = now()->addDays($plan->duration_days);

            if ($existingSub) {
                $startDate = $existingSub->pivot->expires_at;
                $expiresAt = $startDate->copy()->addDays($plan->duration_days);
                
                $user->subscriptionPlans()->updateExistingPivot($plan->id, [
                    'expires_at' => $expiresAt,
                    'transaction_id' => $transaction->id,
                    'auto_renew' => $autoRenew
                ]);
            } else {
                $user->subscriptionPlans()->attach($plan->id, [
                    'id' => (string) Str::uuid(),
                    'transaction_id' => $transaction->id,
                    'started_at' => now(),
                    'expires_at' => $expiresAt,
                    'is_active' => true,
                    'auto_renew' => $autoRenew
                ]);
            }
            $this->broadcast->sendUserNotify($user->id, 'notification_received', [
                'type' => 'subscription_updated',
                'title' => 'Plan Activated',
                'message' => "თქვენი '{$plan->name_ka}'  ახლა აქტიურია.",
                'payload' => [
                    'plan_id' => $plan->id,
                    'action' => 'refresh_access'
                ]
            ]);
            Cache::forget("user_plan_ids_{$user->id}");
            
            return [
            'success' => true,
            'invoice' => [
                'transaction_id' => $transaction->id,
                'date' => $transaction->created_at->toIso8601String(),
                'item_name' => $plan->name_en,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                $field=>$identifier
            ],
            'expires_at' => $expiresAt,
            'remaining_balance' => $account->balance
        ];
        });
    }
    public function purchaseTvLimitUpgrade(User $user, int $quantity = 1): array
{
    $identifier = $user->full_name ?? (string)$user->numeric_id;
    $field = $user->full_name ? 'full_name' : 'customer_id';

    if ($user->company_id !== null) {
        $user->loadMissing('company');
        $identifier = $user->company->name ?? 'Unknown Company';
        $field = 'company_name';
    }
    return DB::transaction(function () use ($user, $quantity, $identifier, $field) {
        $account = $user->account()->lockForUpdate()->first();
        
        if (!$account) {
            throw ValidationException::withMessages(['account' => 'User account not found.']);
        }

        $basePrice = (float) SiteSetting::where('key', 'extra_tv_price')->value('value') ?? 5.00;
        $pricePerSlot = $this->getBestPrice($user, null, $basePrice);
        $totalPrice = $pricePerSlot * $quantity;
        if ($account->balance < $totalPrice) {
            throw ValidationException::withMessages([
                'balance' => "Insufficient balance. Need {$totalPrice} GEL for {$quantity} slots."
            ]);
        }

        $account->balance -= $totalPrice;
        $account->save();

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'plan_id' => null, 
            'amount' => $totalPrice,
            'currency' => 'GEL',
            'status' => 'completed',
            'payment_method' => 'account_balance',
            'metadata' => [
                'type' => 'tv_limit_increase',
                'quantity' => $quantity,
                'price_per_unit' => $pricePerSlot,
                'old_limit' => $user->tv_limit,
                'new_limit' => $user->tv_limit + $quantity,
                'previous_balance' => $account->balance + $totalPrice, 
                'remaining_balance' => $account->balance  
            ],
        ]);

        $user->increment('tv_limit', $quantity);
        $this->broadcast->sendUserNotify($user->id, 'notification_received', [
                'type' => 'tv_limit_updated',
                'new_limit' => $user->tv_limit,
                'message' => "TV დივაისების ლიმიტი გაზრდილია  {$user->tv_limit}-მდე"
            ]);
         return [
            'success' => true,
            'invoice' => [
                'transaction_id' => $transaction->id,
                'date' => $transaction->created_at->toIso8601String(),
                'item_name' => "TV Device Limit Upgrade (+{$quantity})",
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                $field => $identifier
            ],
            'new_limit' => $user->tv_limit,
            'remaining_balance' => $account->balance
        ];
    });
}
     public function getBestPrice(?User $user, ?string $planId, float $originalPrice): float
{

    $discount = Discount::where('is_active', true)
        ->where('target_id', $planId) 
        ->where(function ($query) use ($user) {
            $query->where('is_global', true);
                if ($user) {
                  $query->orWhereHas('users', fn($q) => $q->where('users.id', $user->id));
                }
        })
        ->where(function ($query) {
            $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
        })
        ->orderBy('value', 'asc') 
        ->first();

    if ($discount && $discount->value < $originalPrice) {
        return (float) $discount->value;
    }

    return $originalPrice;
}

public function renewSubscription(UserSubscription $sub): bool
{
    return DB::transaction(function () use ($sub) {
        $user = $sub->user;
        $account = $user->account()->lockForUpdate()->first();

        if (!$account) {
            return false;
        }

        $plan = $sub->plan;
        $price = $this->getBestPrice($user, $plan->id, (float) $plan->price);

        if ($account->balance < $price) {
            return false;
        }

        $account->balance -= $price;
        $account->save();

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => $price,
            'currency' => 'GEL',
            'status' => 'completed',
            'payment_method' => 'auto_renewal',
            'metadata' => [
                'previous_balance' => $account->balance + $price,
                'remaining_balance' => $account->balance,
            ],
        ]);

        $newExpiresAt = $sub->expires_at->addDays($plan->duration_days);

        $user->subscriptionPlans()->updateExistingPivot($plan->id, [
            'expires_at' => $newExpiresAt,
            'transaction_id' => $transaction->id,
        ]);
        return true;
    });
}
public function calculateTvUpgradePrice(User $user, int $quantity = 1): array
{
    $basePrice = (float) (SiteSetting::where('key', 'extra_tv_price')->value('value') ?? 5.00);
    $pricePerSlot = (float) $this->getBestPrice($user, null, $basePrice);

    return [
        'extra_tv_price' => $basePrice,
        'discounted_price' => $pricePerSlot,
        'quantity' => $quantity,
        'total_price' => $pricePerSlot * $quantity,
        'has_discount' => $pricePerSlot < $basePrice
    ];
}
}