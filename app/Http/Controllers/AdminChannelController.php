<?php
namespace App\Http\Controllers;
use App\Models\Channel;
use Illuminate\Http\JsonResponse; 
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\AdminChannelUpdateRequest;
use App\Services\SyncChannelsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AdminChannelController extends Controller{
    public function index(): JsonResponse
{
    
    $channels = Channel::with('category')
        ->orderBy('number', 'asc')
        ->get();

    $formatted = $channels->map(function ($channel) {
        return [
            'uuid'        => $channel->id,          
            'id'          => $channel->external_id, 
            'name'        => $channel->name,        
            'logo'        => $channel->icon_url,
            'number'      => $channel->number,
            'category_en' => $channel->category?->name_en ?? 'Uncategorized',
            'category_ka' => $channel->category?->name_ka ?? 'უკატეგორიო',
            'category_id' => $channel->category_id,
            'is_free'     => (bool) $channel->is_free,
            'is_active'   => (bool) $channel->is_active, 
        ];
    });

    return response()->json($formatted);
}
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
     try {
        $stats = $syncService->migrateFromDump($syncService);

        return response()->json([
            'message' => "Successfully synchronized all data from the legacy dump.",
            'details' => [
                'channels_synced' => $stats['channels'],
                'stream_urls_synced' => $stats['urls'],
                'archives_synced' => $stats['archives']
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Sync failed: ' . $e->getMessage()
        ], 500);
    }
}
}