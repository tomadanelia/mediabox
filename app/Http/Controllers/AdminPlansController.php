<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Models\Channel;
use App\Models\BundleItem;
use \Illuminate\Support\Str;
use App\Models\ServiceBundle;
use Illuminate\Support\Facades\DB;
class AdminPlansController extends Controller
{
    public function allPlans(): JsonResponse
    {
        $plans = SubscriptionPlan::all();
        return response()->json($plans);
    }
    public function getUserPlans(string $userId): JsonResponse
{
    $user = User::findOrFail($userId);

    $plans = $user->subscriptionPlans()->get()->map(function ($plan) {
        return [
            'id' => $plan->id,
            'name_en' => $plan->name_en,
            'name_ka' => $plan->name_ka,
            'price' => $plan->price,
            'started_at' => $plan->pivot->started_at,
            'expires_at' => $plan->pivot->expires_at,
            'is_active' => $plan->pivot->is_active,
            'status' => ($plan->pivot->is_active && $plan->pivot->expires_at > now()) 
                        ? 'active' 
                        : 'expired'
        ];
    });

    return response()->json([
        'user' => [
            'id' => $user->id,
            'username' => $user->username,
            'full_name' => $user->full_name,
        ],
        'plans' => $plans
    ]);
}
    public function addPlan(Request $request)
    {
        $validated = $request->validate([
            'name_ka' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_ka' => 'nullable|string',
            'description_en' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
            'is_public' => 'nullable|boolean', 
        ]);

        $plan = SubscriptionPlan::create($validated);

        return response()->json([
            'message' => 'Subscription plan added successfully',
            'data' => $plan
        ], 201);
    }
    public function editPlan(Request $request, string $planId)
    {
        $plan = SubscriptionPlan::findOrFail($planId);

        $validated = $request->validate([
            'name_ka' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_ka' => 'nullable|string',
            'description_en' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $plan->update($validated);

        return response()->json([
            'message' => 'Subscription plan updated successfully',
            'data' => $plan
        ],200);
    }
    public function disablePlan(string $planId)
    {
        $plan = SubscriptionPlan::findOrFail($planId);
        $plan->update(['is_active' => false]);

        return response()->json([
            'message' => 'Subscription plan disabled successfully',
            'data' => $plan
        ],200);
    }
    public function enablePlan(string $planId)
    {
        $plan = SubscriptionPlan::findOrFail($planId);
        $plan->update(['is_active' => true]);

        return response()->json([
            'message' => 'Subscription plan enabled successfully',
            'data' => $plan
        ],200);
    }
    public function deletePlan(string $planId): JsonResponse
{
    $plan = SubscriptionPlan::findOrFail($planId);

    // Fix: check via bundle_items instead of old plans() relationship
    $channelCount = DB::table('bundle_items')
        ->join('plan_services', 'plan_services.bundle_id', '=', 'bundle_items.bundle_id')
        ->where('plan_services.plan_id', $planId)
        ->where('bundle_items.item_type', 1)
        ->count();

    if ($channelCount > 0) {
        $channels = DB::table('bundle_items')
            ->join('plan_services', 'plan_services.bundle_id', '=', 'bundle_items.bundle_id')
            ->join('channels', 'channels.id', '=', 'bundle_items.item_id')
            ->where('plan_services.plan_id', $planId)
            ->where('bundle_items.item_type', 1)
            ->select('channels.id', 'channels.name', 'channels.external_id', 'channels.number')
            ->get();

        return response()->json([
            'message'    => 'Cannot delete plan: channels are still assigned to it.',
            'error_code' => 'PLAN_HAS_CHANNELS',
            'items'      => $channels->map(fn($c) => [
                'id'           => $c->id,
                'display_name' => "({$c->number}) {$c->name}",
                'external_id'  => $c->external_id,
            ])
        ], 400);
    }

    $users = $plan->users()
        ->select('users.id', 'username', 'numeric_id', 'full_name')
        ->distinct()
        ->get();

    if ($users->isNotEmpty()) {
        return response()->json([
            'message'    => 'Cannot delete plan: users are currently or were previously subscribed.',
            'error_code' => 'PLAN_HAS_USERS',
            'items'      => $users->map(fn($u) => [
                'id'           => $u->id,
                'display_name' => $u->full_name ?? $u->username,
                'numeric_id'   => $u->numeric_id,
            ])
        ], 400);
    }

    $plan->delete(); // cascade removes plan_services rows via FK
    Cache::forget('channel_plan_map');

    return response()->json(['message' => 'Subscription plan deleted successfully'], 200);
}
    public function grantPlanToUser(Request $request, string $userId)
{
    $request->validate([
        'plan_id' => 'required|uuid|exists:subscription_plans,id',
        'days' => 'nullable|integer|min:1' 
    ]);

    $user = User::findOrFail($userId);
    $plan = SubscriptionPlan::findOrFail($request->plan_id);
    $duration = $request->input('days', $plan->duration_days);

    $user->subscriptionPlans()->syncWithoutDetaching([
        $plan->id => [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'started_at' => now(),
            'expires_at' => now()->addDays($duration),
            'is_active' => true,
            'auto_renew' => false,
        ]
    ]);

    Cache::forget("user_plan_ids_{$user->id}");

    return response()->json([
        'message' => "Plan '{$plan->name_en}' granted to user successfully.",
        'expires_at' => now()->addDays($duration)
    ]);
}


public function revokePlanFromUser(Request $request, string $userId)
{
    $request->validate([
        'plan_id' => 'required|uuid|exists:subscription_plans,id',
    ]);

    $user = User::findOrFail($userId);

    $user->subscriptionPlans()->detach($request->plan_id);

    Cache::forget("user_plan_ids_{$user->id}");

    return response()->json([
        'message' => "Plan revoked from user successfully."
    ]);
}
public function attachBundle(Request $request, string $planId): JsonResponse
{
    $request->validate([
        'bundle_id' => 'required|uuid|exists:service_bundles,id'
    ]);

    $plan = SubscriptionPlan::findOrFail($planId);
    
    // Using syncWithoutDetaching avoids duplicates
    $plan->bundles()->syncWithoutDetaching([$request->bundle_id]);

    Cache::forget('channel_plan_map');

    return response()->json(['message' => 'Bundle successfully attached to plan']);
}

/**
 * Detach a Bundle
 */
public function detachBundle(Request $request, string $planId): JsonResponse
{
    $request->validate(['bundle_id' => 'required|uuid']);
    
    $plan = SubscriptionPlan::findOrFail($planId);
    $plan->bundles()->detach($request->bundle_id);

    Cache::forget('channel_plan_map');

    return response()->json(['message' => 'Bundle detached']);
}
}
