<?php
namespace App\Http\Controllers;

use App\Models\RadioChannel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
class RadioController extends Controller
{
    public function index(): JsonResponse
{
    $user = Auth::guard('sanctum')->user();
    $userActivePlanIds = $user ? $user->getActivePlanIds() : [];

    $radios = Cache::remember('global_active_radio_list', 3600, function () {
        return RadioChannel::where('is_active', true)->orderBy('name')->get();
    });

    $radioPlanMap = Cache::remember('radio_plan_map', 3600, function () {
        return DB::table('bundle_items')
            ->where('item_type', 2)
            ->join('plan_services', 'plan_services.bundle_id', '=', 'bundle_items.bundle_id')
            ->select('bundle_items.item_id as radio_id', 'plan_services.plan_id')
            ->get()
            ->groupBy('radio_id')
            ->map(fn($rows) => $rows->pluck('plan_id')->all());
    });

    $userPlanIdMap = array_flip($userActivePlanIds);

    $formatted = $radios->reduce(function ($carry, $radio) use ($userPlanIdMap, $radioPlanMap) {
    $radioPlans = $radioPlanMap[$radio->id] ?? [];
    $hasAccess = $radio->is_free || !empty(array_intersect_key(
        array_flip($radioPlans),
        $userPlanIdMap
    ));

    if (!$radio->is_public && !$hasAccess) {
        return $carry;
    }

    $carry[] = [
        'id'         => $radio->external_id,
        'name'       => $radio->name,
        'logo'       => $radio->icon_url,
        'is_free'    => $radio->is_free,
        'has_access' => $hasAccess,
    ];

    return $carry;
}, []);

    return response()->json($formatted);
}

   public function getStreamUrl(string $id): JsonResponse
{
    $radio = RadioChannel::where('external_id', $id)->firstOrFail();

    if (!$this->canAccessRadio($radio)) {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            return $radio->is_public
                ? response()->json(['message' => 'Login required'], 401)
                : response()->json(['message' => 'Not found'], 404);
        }
        return response()->json(['message' => 'Subscription required'], 403);
    }

    return response()->json([
        'url'  => $radio->stream_url,
        'name' => $radio->name,
    ]);
}

private function canAccessRadio(RadioChannel $radio): bool
{
    if ($radio->is_free) {
        return true;
    }

    $user = Auth::guard('sanctum')->user();
    if (!$user) {
        return false;
    }

    $requiredPlanIds = $radio->getRequiredPlanIds();
    if (empty($requiredPlanIds)) {
        return false;
    }

    return !empty(array_intersect($requiredPlanIds, $user->getActivePlanIds()));
}
}