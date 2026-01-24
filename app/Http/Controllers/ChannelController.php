<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Services\SyncingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
class ChannelController extends Controller
{
    public function __construct(
        protected SyncingService $syncing_service
    ) {}

    public function getChannelFacade(): JsonResponse
    {
        $channels = Channel::where('is_active', true)
            ->orderBy('number', 'asc') 
            ->get()
            ->map(function ($channel) {
                return [
                    'id' => $channel->external_id, 
                    'uuid' => $channel->id,       
                    'name' => $channel->name_ka,   
                    'logo' => $channel->icon_url,
                    'number' => $channel->number,
                    "category"=>$channel->category?->name_en ?? null,
                ];
            });
        return response()->json($channels);
    }
    public function getStreamUrl($id,Request $request):JsonResponse
    {
     $channel = Channel::findOrFail($id);

    $user = Auth::guard('sanctum')->user();
    $allowedPlan=$channel->access_level;
    if ($allowedPlan==="free") {
        $data = $this->syncing_service->getStreamUrl($channel->external_id);
        return response()->json($data);
    }

    if (!$user) {
        return response()->json(['message' => 'Login required for this channel'], 401);
    }

    $userPlan = $user->getActivePlanName(); 

    if (!$userPlan || $userPlan!==$allowedPlan) {
        return response()->json([
            'message' => 'Upgrade to ' . implode(' or ', $allowedPlan) . ' to watch this channel'
        ], 403);
    }

    $data = $this->syncing_service->getStreamUrl($channel->external_id);
    return response()->json($data);


    }
    
     public function programs($id, Request $request): JsonResponse
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        
        $epg = $this->syncing_service->getEpg($id, $date);

        return response()->json($epg);
    }

    public function archive($id, Request $request): JsonResponse
    {
        $timestamp = $request->input('timestamp');

        if (!$timestamp) {
            return response()->json(['message' => 'Timestamp required'], 400);
        }

        $archiveData = $this->syncing_service->getArchiveUrl($id, (int)$timestamp);

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

    
    $requiredPlanIds = \Illuminate\Support\Facades\Cache::remember(
        "channel_plans_{$channel->id}", 
        300, 
        fn() => $channel->plans()->pluck('id')->toArray()
    );

    // If channel is not free but has no plans assigned, nobody can watch it (fail-safe)
    if (empty($requiredPlanIds)) {
        return false;
    }

    // Get User's active plan IDs
    $userPlanIds = $user->getActivePlanIds();

    // 5. Intersection
    // If the user has ANY of the plans required by the channel, allow access.
    return !empty(array_intersect($requiredPlanIds, $userPlanIds));
}

}