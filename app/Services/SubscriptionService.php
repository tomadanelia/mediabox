<?php

namespace App\Services;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\SiteSetting;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use \Illuminate\Support\Str;
class SubscriptionService
{
    public function purchasePlan(User $user, string $planId): array
    {
        return DB::transaction(function () use ($user, $planId) {
            $account = $user->account()->lockForUpdate()->first();
            
            if (!$account) {
                throw ValidationException::withMessages(['account' => 'User account not found.']);
            }

            $plan = SubscriptionPlan::findOrFail($planId);
            $price = $plan->price;
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
                'metadata' => json_encode(['previous_balance' => $account->balance + $price])
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
                    'transaction_id' => $transaction->id 
                ]);
            } else {
                $user->subscriptionPlans()->attach($plan->id, [
                    'id' => (string) Str::uuid(),
                    'transaction_id' => $transaction->id,
                    'started_at' => now(),
                    'expires_at' => $expiresAt,
                    'is_active' => true,
                    'auto_renew' => false
                ]);
            }

            return [
                'message' => 'Subscription purchased successfully',
                'plan_en' => $plan->name_en,
                'plan_ka' => $plan->name_ka,
                'expires_at' => $expiresAt,
                'remaining_balance' => $account->balance
            ];
        });
    }
    public function purchaseTvLimitUpgrade(User $user, int $quantity = 1): array
{
    return DB::transaction(function () use ($user, $quantity) {
        $account = $user->account()->lockForUpdate()->first();
        
        if (!$account) {
            throw ValidationException::withMessages(['account' => 'User account not found.']);
        }

        $pricePerSlot = (float) SiteSetting::where('key', 'extra_tv_price')->value('value') ?? 5.00;
        
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
            'metadata' => json_encode([
                'type' => 'tv_limit_increase',
                'quantity' => $quantity,
                'price_per_unit' => $pricePerSlot,
                'old_limit' => $user->tv_limit,
                'new_limit' => $user->tv_limit + $quantity
            ])
        ]);

        $user->increment('tv_limit', $quantity);

        return [
            'message' => "Successfully added {$quantity} TV slots",
            'added_slots' => $quantity,
            'new_total_limit' => $user->tv_limit,
            'remaining_balance' => $account->balance
        ];
    });
}
}