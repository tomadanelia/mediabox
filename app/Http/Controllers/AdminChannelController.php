<?php
namespace App\Http\Controllers;
use App\Models\Channel;
use Illuminate\Http\JsonResponse; 
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\AdminChannelUpdateRequest;
class AdminChannelController extends Controller{
    public function update(AdminChannelUpdateRequest $request, string $id): JsonResponse
    {
        $channel = Channel::findOrFail($id);

        $channel->update($request->validated());
        foreach ($channel->plans as $plan) {
            Cache::forget("plan_channels_{$plan->id}");
            Cache::forget("plan_channels_formatted_{$plan->id}");
        }

        return response()->json([
            'message' => 'Channel updated successfully',
            'data' => [
                'id' => $channel->id,
                'name_ka' => $channel->name_ka,
                'name_en' => $channel->name_en,
                'icon_url' => $channel->icon_url
            ]
        ]);
    }
}