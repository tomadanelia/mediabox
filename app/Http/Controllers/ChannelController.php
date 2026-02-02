<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Models\Channel;
use App\Services\ConcurrencyService;
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
        $channels = Channel::with('category')
        ->where('is_active', true)
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
    

public function getStreamUrl($id, Request $request, ConcurrencyService $concurrency): JsonResponse
{
    $channel = Channel::where('external_id', $id)->firstOrFail();
    
    if (!$channel->is_free) {
        if (!$this->canAccessChannel($channel)) {
             return response()->json(['message' => 'Subscription required'], 403);
        }
        $request->validate(['device_id' => 'required|string']);
        
        $allowed = $concurrency->heartbeat(
            $request->user()->id,
            $request->input('device_id')
        );

        if (!$allowed) {
            return response()->json(['message' => 'Device limit reached'], 409);
        }
    }

    
    $externalId = $channel->external_id;
    $streamData = $this->syncing_service->getStreamUrl($externalId, $channel->is_free);
    
    if (!$streamData) {
        return response()->json(['message' => 'Stream unavailable'], 404);
    }

    $path = parse_url($streamData['url'], PHP_URL_PATH);
    
    $expires = time() + ($channel->is_free ? 86400 : 14400);
    
    $ip = $request->ip();
    $secret = config('services.nginx.secure_link_secret'); 
    
    $stringToSign = "{$expires}{$path}{$ip} {$secret}";
    $md5 = base64_encode(md5($stringToSign, true));
    $md5 = str_replace(['+', '/', '='], ['-', '_', ''], $md5);
    
    $finalUrl = $streamData['url'] . "?md5={$md5}&expires={$expires}";

    return response()->json([
        'url' => $finalUrl,
        'heartbeat_interval' => $channel->is_free ? null : 120 
    ]);
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

    public function archive($id, Request $request, ConcurrencyService $concurrency): JsonResponse
{
    $timestamp = $request->input('timestamp');
    if (!$timestamp) {
        return response()->json(['message' => 'Timestamp required'], 400);
    }
    
    $channel = Channel::where('external_id', $id)->firstOrFail();

    if (!$channel->is_free) {
        if (!$this->canAccessChannel($channel)) {
            $user = Auth::guard('sanctum')->user();
            $status = $user ? 403 : 401;
            $message = $user ? 'Subscription required' : 'Login required';
            return response()->json(['message' => $message], $status);
        }

        $request->validate(['device_id' => 'required|string|max:64']);
        
        $allowed = $concurrency->heartbeat(
            $request->user()->id,
            $request->input('device_id')
        );

        if (!$allowed) {
            return response()->json([
                'message' => 'Device limit reached. Stop watching on other devices.'
            ], 409);
        }
    }

    $externalId = $channel->external_id;
    $archiveData = $this->syncing_service->getArchiveUrl(
        $externalId, 
        (int)$timestamp, 
        $channel->is_free
    );

    if (!$archiveData) {
        return response()->json(['message' => 'Archive unavailable'], 404);
    }

    
    $path = parse_url($archiveData['url'], PHP_URL_PATH);
    $expires = time() + ($channel->is_free ? 86400 : 14400);
    $ip = $request->ip();
    $secret = config('services.nginx.secure_link_secret'); 
    
    $stringToSign = "{$expires}{$path}{$ip} {$secret}";
    $md5 = base64_encode(md5($stringToSign, true));
    $md5 = str_replace(['+', '/', '='], ['-', '_', ''], $md5);
    
  
    $separator = (parse_url($archiveData['url'], PHP_URL_QUERY) == NULL) ? '?' : '&';
    
    $finalUrl = $archiveData['url'] . "{$separator}md5={$md5}&expires={$expires}";
    
    return response()->json([
        'url' => $finalUrl,
        'heartbeat_interval' => $channel->is_free ? null : 120 
    ]);
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