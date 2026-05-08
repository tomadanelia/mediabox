<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Models\Channel;
use Illuminate\Support\Facades\Cache;
use App\Models\ChannelCategory; 
use App\Services\ConcurrencyService;
use App\Services\SyncingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
class ChannelController extends Controller
{
    public function __construct(
        protected SyncingService $syncing_service,
        protected ConcurrencyService $concurrencyService
    ) {}
    public function initVisitor()
{
    session(['is_web_visitor' => true]);

    return response()->json([
        'status' => 'ready',
        'message' => 'Visitor session initialized'
    ]);
}
    public function getChannelFacade(): JsonResponse
{
    Auth::shouldUse('sanctum');
    $user = request()->user();
    $freePlanId = '00000000-0000-0000-0000-000000000000';
    $hasHandshake = session('is_web_visitor', false);

    $allChannels = Cache::remember('global_active_channels_list', 3600, function () {
        return Channel::with('category')->where('is_active', true)->orderBy('number', 'asc')->get();
    });

    $channelPlanMap = Cache::remember('channel_plan_map', 3600, function () {
        return DB::table('bundle_items')
            ->where('item_type', 1)
            ->join('plan_services', 'plan_services.bundle_id', '=', 'bundle_items.bundle_id')
            ->select('bundle_items.item_id as channel_id', 'plan_services.plan_id')
            ->get()->groupBy('channel_id')->map(fn($rows) => $rows->pluck('plan_id')->all());
    });

    $userPlanIds = $user ? $user->getActivePlanIds() : [];
    $userPlanIdMap = array_flip($userPlanIds);

    [$formattedChannels, $accessibleIds] = $allChannels->reduce(
        function ($carry, $channel) use ($userPlanIdMap, $channelPlanMap, $freePlanId, $hasHandshake, $user) {
            [$formatted, $accessibleIds] = $carry;

            $channelPlans = $channelPlanMap[$channel->id] ?? [];
            if (empty($channelPlans)) return $carry; // Ignore channels with no plan

            $isFreeChannel = in_array($freePlanId, $channelPlans);
            
            $hasPaidPlan = !empty(array_intersect_key(array_flip($channelPlans), $userPlanIdMap));
            
            if ($user) {
                $isAccessible = $isFreeChannel || $hasPaidPlan;
            } else {
                $isAccessible = $isFreeChannel && $hasHandshake;
            }

            if (!$channel->is_public && !$isAccessible) {
                return $carry;
            }

            if ($isAccessible) {
                $accessibleIds[] = $channel->external_id;
            }

            $formatted[] = [
                'uuid'          => $channel->id,
                'id'            => $channel->external_id,
                'name'          => $channel->name,   
                'logo'          => $channel->icon_url,
                'number'        => $channel->number,
                'category_ka'   => $channel->category?->name_ka,
                'is_accessible' => $isAccessible,
            ];

            return [$formatted, $accessibleIds];
        },
        [[], []]
    );

    return response()->json([
        'channels'                => $formattedChannels,
        'accessible_external_ids' => $accessibleIds,
    ]);
}
public function getChannelPlans($id): JsonResponse
{
    $channel = Channel::where('external_id', $id)->firstOrFail();

    $plans = DB::table('subscription_plans')
        ->join('plan_services', 'plan_services.plan_id', '=', 'subscription_plans.id')
        ->join('bundle_items', 'bundle_items.bundle_id', '=', 'plan_services.bundle_id')
        ->where('bundle_items.item_type', 1)
        ->where('bundle_items.item_id', $channel->id)
        ->where('subscription_plans.is_active', true)
        ->select('subscription_plans.id', 'name_ka', 'name_en', 'price', 'duration_days')
        ->get();

    return response()->json([
        'external_id'     => $id,
        'channel_name'    => $channel->name,   
        'is_free'         => $channel->is_free,
        'required_plans'  => $plans,
    ]);
}
    
public function getCategories(): JsonResponse
{
    $categories = ChannelCategory::all(); 
    
    return response()->json($categories);
}

public function getStreamUrl($id, Request $request): JsonResponse
{
  
    $channel = Channel::where('external_id', $id)->firstOrFail();
    
    if (!$this->canAccessChannel($channel)) {
             return response()->json(['message' => 'Subscription required'], 403);
    }
    
     $streamData = $this->syncing_service->getStreamUrl($channel->external_id, $request->ip());
    
    if (!$streamData) {
        return response()->json(['message' => 'Stream unavailable'], 404);
    }
  

    return response()->json($streamData);
}

    public function programs($id, Request $request): JsonResponse
    {
     $request->validate([
    'date' => ['nullable', 'date'],
   ]);

     $channel = Channel::where('external_id', $id)->firstOrFail();

    $date = $request->input('date', now()->toDateString());

    return response()->json(
        $this->syncing_service->getEpg($channel->external_id, $date)
    );
    }



    public function allPrograms($id, Request $request): JsonResponse
    {
    $channel = Channel::where('external_id', $id)->firstOrFail();
    $request->validate([
        'start' => ['nullable', 'integer'],
        'end'   => ['nullable', 'integer'],
    ]);

    $start = $request->input('start', now()->subDays(7)->startOfDay()->timestamp);
    $end   = $request->input('end', now()->endOfDay()->timestamp);

    return response()->json(
        $this->syncing_service->getAllEpg($channel->external_id, $start, $end)
    );
    }

    public function archive($id, Request $request): JsonResponse
{
    $timestamp = $request->input('timestamp');
    if (!$timestamp) {
        return response()->json(['message' => 'Timestamp required'], 400);
    }
    
    $channel = Channel::where('external_id', $id)->firstOrFail();

    if (!$this->canAccessChannel($channel)) {
    return response()->json(['message' => 'Subscription required'], 403);

    }

   $archiveData = $this->syncing_service->getArchiveUrl(
        $channel->external_id, 
        (int)$timestamp, 
        $request->ip()
    );

    if (!$archiveData) {
        return response()->json(['message' => 'Archive unavailable'], 404);
    }

    return response()->json($archiveData);
}
   private function canAccessChannel(Channel $channel): bool
{
    Auth::shouldUse('sanctum');
    $user = request()->user(); 
    $requiredPlanIds = Cache::remember("channel_plans_{$channel->id}", 3600, function() use ($channel) {
        return DB::table('bundle_items')
            ->where('item_type', 1)
            ->where('item_id', $channel->id)
            ->join('plan_services', 'plan_services.bundle_id', '=', 'bundle_items.bundle_id')
            ->pluck('plan_id')
            ->toArray();
    });

    if (empty($requiredPlanIds)) {
        return false; 
    }

    $freePlanId = '00000000-0000-0000-0000-000000000000';
    $isFreeChannel = in_array($freePlanId, $requiredPlanIds);

    if ($user) {
        return $isFreeChannel || !empty(array_intersect($requiredPlanIds, $user->getActivePlanIds()));
    }
    return $isFreeChannel && (bool) session('is_web_visitor');
}

}