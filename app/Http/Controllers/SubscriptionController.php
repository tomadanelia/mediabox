<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)->get();
        return response()->json($plans);
    }
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
                                ->wherePivot('is_active', true)
                                ->wherePivot('expires_at', '>', now())
                                ->first();

            $startDate = now();
            $expiresAt = now()->addDays($plan->duration_days);

            if ($existingSub) {
                $startDate = $existingSub->pivot->expires_at;
                $expiresAt = $startDate->copy()->addDays($plan->duration_days);
                
                UserSubscription::where('id', $existingSub->pivot->id)->update([
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
}