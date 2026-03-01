<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Cache;
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
    public function myPlans(Request $request): JsonResponse
{
    $plans = $request->user()->subscriptionPlans()
        ->wherePivot('is_active', true)
        ->wherePivot('expires_at', '>', now())
        ->get()
        ->map(function ($plan) {
            return [
                'plan_id' => $plan->id,
                'name_en' => $plan->name_en,
                'name_ka' => $plan->name_ka,
                'price'   => $plan->price,
                'expires_at' => $plan->pivot->expires_at, 
                'days_left'  => now()->diffInDays($plan->pivot->expires_at, false)
            ];});
    return response()->json($plans);
}
    public function purchase(Request $request): JsonResponse
{
    $request->validate([
        'plan_id' => 'required|uuid|exists:subscription_plans,id'
    ]);

    try {
        $result = $this->subscriptionService($request->user(), $request->plan_id);
        return response()->json($result, 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => $e->getMessage()
        ], 422);
    }
}
       public function getChannelsForPlan(string $planId)
{
    $channels = Cache::remember("plan_channels_{$planId}", 120, function () use ($planId) {
        $plan = SubscriptionPlan::findOrFail($planId);
        return $plan->channels()->get();
    });

    return response()->json([
        'message' => 'Channels retrieved successfully',
        'channels' => $channels
    ], 200);
}
}