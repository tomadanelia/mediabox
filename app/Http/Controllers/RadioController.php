<?php
namespace App\Http\Controllers;

use App\Models\RadioChannel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RadioController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        $userActivePlanIds = $user ? $user->getActivePlanIds() : [];

        $radios = RadioChannel::where('is_active', true)->orderBy('name')->get();

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

    public function getStreamUrl(string $id): JsonResponse
    {
        $radio = RadioChannel::where('external_id', $id)->firstOrFail();

        if (!$radio->is_free) {
            $user = Auth::guard('sanctum')->user();
            if (!$user) return response()->json(['message' => 'Login required'], 401);

            $required = $radio->getRequiredPlanIds();
            if (empty(array_intersect($required, $user->getActivePlanIds()))) {
                return response()->json(['message' => 'Subscription required'], 403);
            }
        }

        return response()->json([
            'url' => $radio->stream_url,
            'name' => $radio->name
        ]);
    }
}