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

    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
        ]);

        $user = $request->user(); 
        $result = $this->subscriptionService->purchasePlan($user, $request->plan_id);

        return response()->json($result);
    }
}