<?php
namespace App\Http\Controllers;

use App\Models\RadioChannel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
class AdminRadioController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(RadioChannel::all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'external_id' => 'required|integer|unique:radio_channels',
            'name'        => 'required|string',
            'stream_url'  => 'required|url',
            'icon_url'    => 'nullable|url',
            'is_active'   => 'boolean',
            'is_free'     => 'boolean',
            'is_public'   => 'boolean',
        ]);

        $radio = RadioChannel::create($validated);
        return response()->json($radio, 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $radio = RadioChannel::findOrFail($id);
        
        $validated = $request->validate([
            'name'        => 'sometimes|string',
            'stream_url'  => 'sometimes|url',
            'external_id' => ['sometimes', 'integer', 'min:0', 'max:200','unique:radio_channels,external_id,' . $radio->id,],
            'icon_url'    => 'nullable|url',
            'is_active'   => 'boolean',
            'is_free'     => 'boolean',
            'is_public' => 'boolean', 

        ]);

        $radio->update($validated);
        Cache::forget('global_active_radio_list');
        Cache::forget('radio_plan_map');
        return response()->json($radio);
    }

    public function destroy($id): JsonResponse
    {
        RadioChannel::findOrFail($id)->delete();
        return response()->json(['message' => 'Radio deleted']);
    }
    public function toggleActive($id): JsonResponse
{
    $radio = RadioChannel::findOrFail($id);
    $radio->is_active = !$radio->is_active;
    $radio->save();

    Cache::forget('global_active_radio_list');
    Cache::forget('radio_plan_map');

    return response()->json([
        'message' => 'Radio status updated',
        'is_active' => $radio->is_active
    ]);
}

    public function togglePublic($id): JsonResponse
{
    $radio = RadioChannel::findOrFail($id);
    $radio->is_public = !$radio->is_public;
    $radio->save();

    Cache::forget('global_active_radio_list');
    Cache::forget('radio_plan_map');
    return response()->json([
        'message' => 'Visibility updated',
        'is_public' => $radio->is_public
    ]);
}
}