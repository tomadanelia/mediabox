<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\CachedPersonalAccessToken;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\GeoLocationService;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected GeoLocationService $geoService

    ) {}

    public function index(): JsonResponse
{
    Auth::shouldUse('sanctum');
    $user = request()->user();
    $isInternational = $geoService->isInternational($request->ip());
    $scope = $isInternational ? 'intl' : 'ge';
    $plans = SubscriptionPlan::where('is_active', true)
    ->where('is_public', true)
    ->where(function ($query) use ($scope) {
        $query->where('location_scope', 'all')
              ->orWhere('location_scope', $scope);
    })
    ->get();

    $formattedPlans = $plans->map(function ($plan) use ($user) {
        $originalPrice = (float) $plan->price;
        $currentPrice = $this->subscriptionService->getBestPrice($user, $plan->id, $originalPrice);

        return [
            'id' => $plan->id,
            'name_ka' => $plan->name_ka,
            'name_en' => $plan->name_en,
            'description_ka' => $plan->description_ka,
            'description_en' => $plan->description_en,
            'duration_days' => $plan->duration_days,
            'price' => $originalPrice,
            'discounted_price' => $currentPrice,
            'is_active' => $plan->is_active
        ];
    });

    return response()->json($formattedPlans);
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
        'plan_id' => 'required|uuid|exists:subscription_plans,id',
        'auto_renew' => 'nullable|boolean'
    ]);

    try {
    $autoRenew = $request->boolean('auto_renew', true);
    $result = $result = $this->subscriptionService->purchasePlan(
    $request->user(),
    $request->plan_id,
    $autoRenew
);
        return response()->json($result, 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => $e->getMessage()
        ], 422);
    }
}
       public function getChannelsForPlan(string $planId): JsonResponse
{
    $data = Cache::remember("plan_content_details_{$planId}", 120, function () use ($planId) {
        $plan = SubscriptionPlan::findOrFail($planId);

        $bundles = $plan->bundles()->where('is_active', true)->get();

        return $bundles->map(function ($bundle) {
            $channels = DB::table('bundle_items')
                ->join('channels', 'channels.id', '=', 'bundle_items.item_id')
                ->where('bundle_items.bundle_id', $bundle->id)
                ->where('bundle_items.item_type', 1)
                ->select('channels.id', 'channels.name', 'channels.number', 'channels.icon_url', 'channels.external_id')
                ->get();

            $radios = DB::table('bundle_items')
                ->join('radio_channels', 'radio_channels.id', '=', 'bundle_items.item_id')
                ->where('bundle_items.bundle_id', $bundle->id)
                ->where('bundle_items.item_type', 2)
                ->select('radio_channels.id', 'radio_channels.name', 'radio_channels.icon_url')
                ->get();

            return [
                'bundle_id'   => $bundle->id,
                'bundle_name' => $bundle->name,
                'bundle_type' => $bundle->type,
                'items'       => [
                    'channels' => $channels,
                    'radios'   => $radios
                ]
            ];
        });
    });

    return response()->json([
        'plan_id' => $planId,
        'bundles' => $data
    ], 200);
}
public function upgradeTvLimit(Request $request): JsonResponse
{
    $request->validate([
        'quantity' => 'required|integer|min:1|max:10' 
    ]);

    try {
        $result = $this->subscriptionService->purchaseTvLimitUpgrade(
            $request->user(), 
            $request->integer('quantity')
        );
        return response()->json($result, 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => $e->getMessage()
        ], 422);
    }
}
public function getTvLimitPrice(Request $request): JsonResponse
{
    $request->validate([
        'quantity' => 'nullable|integer|min:1|max:10'
    ]);

    $user = $request->user();
    $quantity = $request->integer('quantity', 1);

    $priceDetails = $this->subscriptionService->calculateTvUpgradePrice($user, $quantity);

    return response()->json($priceDetails);
}
   public function getTvDevices(Request $request): JsonResponse
{
    $user = $request->user();

    $activeTvs = $user->getActiveTvDevices();

    return response()->json([
        'tv_limit' => $user->tv_limit,
        'active_count' => $activeTvs->count(),
        'slots_remaining' => max(0, $user->tv_limit - $activeTvs->count()),
        'devices' => $activeTvs
    ]);
}
   public function logoutTvDevice(Request $request): JsonResponse
{
    $request->validate([
        'device_id' => 'required|string|exists:user_devices,device_id',
    ]);

    $user = $request->user();
    $token = $user->tokens()
        ->where('name', 'tv_apk')
        ->where('device_id', $request->device_id)
        ->first();

    if (!$token) {
        return response()->json(['message' => 'No active session found for this device'], 404);
    }
    $token->delete();
    $remaining_slots=$user->tv_limit - $user->tokens()->where('name', 'tv_apk')->count();
    return response()->json([
        'message' => 'TV device logged out successfully',
        'device_id' => $request->device_id,
        'remaining_slots' => $remaining_slots
    ]);
}
public function getTransactions(Request $request): JsonResponse
{
    $transactions = PaymentTransaction::where('user_id', $request->user()->id)
        ->with('plan:id,name_ka,name_en') 
        ->latest() 
        ->paginate(15);

    $transactions->getCollection()->transform(function ($item) {
        $itemName = $item->plan 
            ? $item->plan->name_en 
            : ($item->metadata['item_name'] ?? 'Account Adjustment');

        return [
            'id' => $item->id,
            'item_name' => $itemName,
            'amount' => $item->amount,
            'currency' => $item->currency,
            'status' => $item->status,
            'payment_method' => str_replace('_', ' ', $item->payment_method),
            'date' => $item->created_at->format('Y-m-d H:i'),
            'metadata' => $item->metadata 
        ];
    });

    return response()->json($transactions);
}

}