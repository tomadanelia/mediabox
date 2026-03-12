<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Models\Channel;
use App\Models\ChannelCategory; 
use App\Services\ConcurrencyService;
use App\Services\SyncingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
class ChannelController extends Controller
{
    public function __construct(
        protected SyncingService $syncing_service,
        protected ConcurrencyService $concurrencyService
    ) {}

    public function getChannelFacade(): JsonResponse
{
    $user = Auth::guard('sanctum')->user();
    
    $query = Channel::with('category')
        ->where('is_active', true)
        ->orderBy('number', 'asc');

    if ($user) {
        $query->with('plans:subscription_plans.id');
    }

    $allChannels = $query->get();
    $userActivePlanIds = $user ? $user->getActivePlanIds() : [];

    $accessibleIds = $allChannels->filter(function ($channel) use ($userActivePlanIds) {
        if ($channel->is_free) return true;
        
        if (!empty($userActivePlanIds)) {
            $requiredPlanIds = $channel->relationLoaded('plans') 
                ? $channel->plans->pluck('id')->toArray() 
                : $channel->getRequiredPlanIds();
                
            return !empty(array_intersect($requiredPlanIds, $userActivePlanIds));
        }
        return false;
    })->pluck('external_id')->values()->toArray();

    $formattedChannels = $allChannels->map(function ($channel) {
        return [
            'uuid' => $channel->id,
            'id' => $channel->external_id, 
            'name' => $channel->name_ka,   
            'logo' => $channel->icon_url,
            'number' => $channel->number,
            'category_en' => $channel->category?->name_en ?? null,
            'category_ka' => $channel->category?->name_ka ?? null,
            'category_id'=>$channel->category?->id,
            'is_free' => $channel->is_free,
        ];
    });

    return response()->json([
        'channels' => $formattedChannels,
        'accessible_external_ids' => $accessibleIds
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
      \Illuminate\Support\Facades\Log::info('Stream Request Debug', [
        'url' => $request->fullUrl(),
        'method' => $request->method(),
        'headers' => $request->headers->all(),
        'payload' => $request->all(), // This shows the JSON body or Query params
        'user_id' => $request->user()?->id
    ]);
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
            $user = Auth::guard('sanctum')->user();
            $status = $user ? 403 : 401;
            $message = $user ? 'Subscription required' : 'Login required';
            return response()->json(['message' => $message], $status);
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

    $user = Auth::guard('sanctum')->user();
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


public function heartbeat(Request $request): JsonResponse
{
    $request->validate([
        'device_id' => 'required|string|max:64',
    ]);

    $allowed = $this->concurrencyService->heartbeat(
        $request->user()->id,
        $request->input('device_id'),
        $request->ip()
    );

    if (!$allowed) {
        return response()->json(['message' => 'Session expired or limit reached'], 409);
    }

    return response()->json(['status' => 'ok']);
}


}