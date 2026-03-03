<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Models\Channel;

class AdminPlansController extends Controller
{
    public function allPlans(): JsonResponse
    {
        $plans = SubscriptionPlan::all();
        return response()->json($plans);
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
    public function deletePlan(string $planId)
{
    $plan = SubscriptionPlan::findOrFail($planId);

    if ($plan->channels()->count() > 0) {
        return response()->json([
            'message' => 'Cannot delete plan: it still has channels assigned.'
        ], 400); 
    }

    $plan->delete();

    Cache::forget("plan_channels_{$planId}");

    return response()->json([
        'message' => 'Subscription plan deleted successfully'
    ], 200);
}
    public function addChannelsToPlan(Request $request, string $planId)
    {
        $plan = SubscriptionPlan::findOrFail($planId);

        $validated = $request->validate([
            'channel_ids' => 'required|array',
            'channel_ids.*' => 'uuid|exists:channels,id',
        ]);

        $plan->channels()->syncWithoutDetaching($validated['channel_ids']);
        Channel::whereIn('id', $validated['channel_ids'])->update(['is_free' => false]);
        Cache::forget("plan_channels_{$planId}");
        foreach($validated['channel_ids'] as $id) {
        Cache::forget("channel_plans_{$id}");
    }
        return response()->json([
            'message' => 'Channels added to subscription plan successfully',
            'data' => $plan->channels
        ],200);
    }
    public function removeChannelsFromPlan(Request $request, string $planId)
    {
        $plan = SubscriptionPlan::findOrFail($planId);

        $validated = $request->validate([
            'channel_ids' => 'required|array',
            'channel_ids.*' => 'uuid|exists:channels,id',
        ]);

        $plan->channels()->detach($validated['channel_ids']);
        $channelsWithoutPlans = Channel::whereIn('id', $validated['channel_ids'])
        ->whereDoesntHave('plans')
        ->pluck('id');
        if ($channelsWithoutPlans->isNotEmpty()) {
        Channel::whereIn('id', $channelsWithoutPlans)->update(['is_free' => true]);
    }
        Cache::forget("plan_channels_{$planId}");
        foreach($validated['channel_ids'] as $id) {
            Cache::forget("channel_plans_{$id}");
        }
        return response()->json([
            'message' => 'Channels removed from subscription plan successfully',
            'data' => $plan->channels()->get()
        ],200);
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

}
