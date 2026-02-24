<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SubscriptionPlan;
class AdminPlansController extends Controller
{
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
    public function addChannelsToPlan(Request $request, string $planId)
    {
        $plan = SubscriptionPlan::findOrFail($planId);

        $validated = $request->validate([
            'channel_ids' => 'required|array',
            'channel_ids.*' => 'uuid|exists:channels,id',
        ]);

        $plan->channels()->syncWithoutDetaching($validated['channel_ids']);
        Cache::forget("plan_channels_{$planId}");
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
        Cache::forget("plan_channels_{$planId}");
        return response()->json([
            'message' => 'Channels removed from subscription plan successfully',
            'data' => $plan->channels
        ],200);
    }

}
