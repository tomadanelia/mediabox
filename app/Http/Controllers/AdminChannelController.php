<?php
namespace App\Http\Controllers;
use App\Models\Channel;
use Illuminate\Http\JsonResponse; 
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\AdminChannelUpdateRequest;
use App\Services\SyncChannelsService;
class AdminChannelController extends Controller{
    public function store(Request $request, SyncChannelsService $syncService): JsonResponse
{
    $validated = $request->validate([
        'external_id' => 'required|string|unique:channels,external_id',
        'number'      => 'required|integer',
        'name'        => 'required|string|max:255',
        'icon_url'    => 'nullable|string|url',
        'category_id' => 'required|uuid|exists:channel_categories,id',
        'is_active'   => 'boolean',
        'is_free'     => 'required|boolean',
        ]);

    $channel = DB::transaction(function () use ($validated, $syncService) {
        $channel = Channel::create($validated);

        $syncService->assignDefaultPlans($channel, $validated['is_free']);

        return $channel;
    });

    return response()->json([
        'message' => 'Channel created and assigned to ' . ($validated['is_free'] ? 'Free' : 'Standard') . ' package.',
        'data' => $channel
    ], 201);
}
    public function toggleActive(Request $request, string $id): JsonResponse
{
    $channel = Channel::findOrFail($id);

    $channel->is_active = !$channel->is_active;
    $channel->save();
    Cache::forget("plan_channels_formatted_*");
    Cache::forget("channel_stream_{$channel->external_id}_*");

    return response()->json([
        'message' => 'Channel ' . ($channel->is_active ? 'enabled' : 'disabled') . ' successfully.',
        'is_active' => $channel->is_active
    ]);
}
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
                'name' => $channel->name,
                'icon_url' => $channel->icon_url,
                'is_active' => $channel->is_active,
            ]
        ]);
    }
    public function sync(SyncChannelsService $syncService): JsonResponse
{
    $count = $syncService->syncChannels();

    return response()->json([
        'message' => "Successfully synced {$count} channels from legacy API.",
        'count' => $count
    ]);
}
}