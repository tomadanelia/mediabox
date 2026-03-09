<?php

namespace App\Http\Controllers;

use App\Models\RadioChannel;
use App\Services\RadioSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class RadioController extends Controller
{
    public function __construct(protected RadioSyncService $radioService) {}

    public function index(): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        $userActivePlanIds = $user ? $user->getActivePlanIds() : [];

        $radios = RadioChannel::where('is_active', true)->orderBy('external_id')->get();

        $formatted = $radios->map(function ($radio) use ($userActivePlanIds) {
            $hasAccess = $radio->is_free;

            if (!$hasAccess && !empty($userActivePlanIds)) {
                $required = $radio->getRequiredPlanIds();
                $hasAccess = !empty(array_intersect($required, $userActivePlanIds));
            }

            return [
                'id' => $radio->external_id,
                'name' => $radio->name,
                'logo' => $radio->icon_url,
                'is_free' => $radio->is_free,
                'has_access' => $hasAccess
            ];
        });

        return response()->json($formatted);
    }

    public function getStreamUrl(string $id, Request $request): JsonResponse
    {
        $radio = RadioChannel::where('external_id', $id)->firstOrFail();

        if (!$radio->is_free) {
            $user = Auth::guard('sanctum')->user();
            if (!$user) return response()->json(['message' => 'Login required'], 401);

            $required = $radio->getRequiredPlanIds();
            $userPlans = $user->getActivePlanIds();

            if (empty(array_intersect($required, $userPlans))) {
                return response()->json(['message' => 'Subscription required'], 403);
            }
        }

        $cacheKey = "radio_stream_{$id}_{$request->ip()}";
        $stream = Cache::remember($cacheKey, 300, function() use ($id, $request) {
            return $this->radioService->getRadioStream($id, $request->ip());
        });

        if (!$stream) return response()->json(['message' => 'Stream unavailable'], 404);

        return response()->json($stream);
    }
}