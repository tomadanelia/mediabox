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
class ChannelController extends Controller
{
    public function __construct(
        protected SyncingService $syncing_service,
        protected ConcurrencyService $concurrencyService
    ) {}

    public function getChannelFacade(): JsonResponse
{
    Auth::shouldUse('sanctum');
    $user = request()->user();
    $freePlanId = '00000000-0000-0000-0000-000000000000';

    $allChannels = Cache::remember('global_active_channels_list', 3600, function () {
        return Channel::with(['category', 'plans:subscription_plans.id'])
            ->where('is_active', true)
            ->orderBy('number', 'asc')
            ->get();
    });

    $userPlanIdMap = array_flip(
        array_merge($user ? $user->getActivePlanIds() : [], [$freePlanId])
    );

   $accessibleIds = [];

   [$formattedChannels, $accessibleIds] = $allChannels->reduce(
    function ($carry, $channel) use ($userPlanIdMap) {
        [$formatted, $accessibleIds] = $carry;

        $isAccessible = $channel->plans->contains(
            fn($plan) => isset($userPlanIdMap[$plan->id])
        );

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
            'category_en'   => $channel->category?->name_en,
            'category_ka'   => $channel->category?->name_ka,
            'category_id'   => $channel->category?->id,
            'is_free'       => $channel->is_free,
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
    $plans = $channel->plans()->where('is_active', true)->get([
        'subscription_plans.id', 
        'name_ka', 
        'name_en', 
        'price', 
        'duration_days'
    ]);

    return response()->json([
        'external_id' => $id,
        'channel_name_ka' => $channel->name_ka,
        'channel_name_en' => $channel->name_en,
        'is_free' => $channel->is_free,
        'required_plans' => $plans
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
    
    if (!$channel->is_free && !$this->canAccessChannel($channel)) {
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

    if (!$channel->is_free && !$this->canAccessChannel($channel)) {
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
    if ($channel->is_free) {
        return true;
    }
    Auth::shouldUse('sanctum');
    $user = request()->user();
    if (!$user) {
        return false;
    }
    $requiredPlanIds = $channel->getRequiredPlanIds();

    if (empty($requiredPlanIds)) {
        return false;
    }

    $userPlanIds = $user->getActivePlanIds();
    return !empty(array_intersect($requiredPlanIds, $userPlanIds));
}


}