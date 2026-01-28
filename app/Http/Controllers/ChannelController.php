<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
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
    if (!$this->canAccessChannel($channel)) {
        $user = Auth::guard('sanctum')->user();
        $status = $user ? 403 : 401;
        $message = $user ? 'Subscription required' : 'Login required';
        return response()->json(['message' => $message], $status);
    }
    $data = $this->syncing_service->getStreamUrl($id);
    return response()->json($data);

    }
    
    public function programs($id, Request $request): JsonResponse
    {

        $date = $request->input('date', now()->toDateString());
        
        $epg = $this->syncing_service->getEpg($id, $date);

        return response()->json($epg);
    }

    public function archive($id, Request $request): JsonResponse
    {
        $access = $this->canAccessChannel(Channel::findOrFail($id));
        if (!$access) {
            return response()->json(['message' => 'Subscription required'], 403);
        }
        $archiveData = $this->syncing_service->getArchiveUrl($id);

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

}