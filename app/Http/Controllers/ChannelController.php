<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Services\SyncingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChannelController extends Controller
{
    public function __construct(protected SyncingService $syncing_service) {}

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
                    'number' => $channel->number
                ];
            });
        return response()->json($channels);
    }
    public function getStreamUrl($id,Request $request):JsonResponse
    {
    $data=$this->syncing_service->getStreamUrl($id);

    if (!$data || !$data['url']) {
            return response()->json(['message' => 'Stream unavailable'], 404);
        }
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

}