<?php
namespace App\Http\Controllers;

use App\Models\ServiceBundle;
use App\Models\BundleItem;
use App\Models\AppModule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;


class AdminBundleController extends Controller
{
    public function listBundles(): JsonResponse
    {
        $bundles = ServiceBundle::withCount('items')
            ->with('plans:subscription_plans.id,name_en,name_ka')
            ->get();

        return response()->json($bundles);
    }

    public function showBundle(string $bundleId): JsonResponse
{
    $bundle = ServiceBundle::with(['plans:subscription_plans.id,name_en,name_ka'])
        ->withCount('items')
        ->findOrFail($bundleId);

    $channels = DB::table('bundle_items')
        ->join('channels', 'channels.id', '=', 'bundle_items.item_id')
        ->where('bundle_items.bundle_id', $bundleId)
        ->where('bundle_items.item_type', 1)
        ->select('channels.id', 'channels.name', 'channels.number', 'channels.icon_url', 'channels.external_id', 'channels.is_active')
        ->get();

    $radioChannels = DB::table('bundle_items')
        ->join('radio_channels', 'radio_channels.id', '=', 'bundle_items.item_id')
        ->where('bundle_items.bundle_id', $bundleId)
        ->where('bundle_items.item_type', 2)
        ->select('radio_channels.id', 'radio_channels.name', 'radio_channels.icon_url', 'radio_channels.is_active')
        ->get();

    $modules = DB::table('bundle_items')
        ->join('app_modules', 'app_modules.id', '=', 'bundle_items.item_id')
        ->where('bundle_items.bundle_id', $bundleId)
        ->where('bundle_items.item_type', 3)
        ->select('app_modules.id', 'app_modules.name', 'app_modules.slug', 'app_modules.is_active')
        ->get();

    return response()->json([
        'id'          => $bundle->id,
        'slug'        => $bundle->slug,
        'name'        => $bundle->name,
        'type'        => $bundle->type,
        'is_active'   => $bundle->is_active,
        'items_count' => $bundle->items_count,
        'plans'       => $bundle->plans,
        'items' => [
            'channels'       => $channels,
            'radio_channels' => $radioChannels,
            'modules'        => $modules,
        ]
    ]);
}

    public function createBundle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug'      => 'required|string|unique:service_bundles,slug',
            'name'      => 'required|string',
            'type'      => 'required|in:tv,radio,module',
            'is_active' => 'boolean',
        ]);

        $bundle = ServiceBundle::create($validated);

        return response()->json($bundle, 201);
    }
public function update(Request $request, string $id): JsonResponse
{
    $bundle = ServiceBundle::findOrFail($id);

    $validated = $request->validate([
        'name'      => 'sometimes|string|max:255',
        'slug'      => 'sometimes|string|unique:service_bundles,slug,' . $id,
        'type'      => 'sometimes|in:tv,radio,module',
        'is_active' => 'sometimes|boolean'
    ]);

    $bundle->update($validated);

    Cache::forget('global_active_channels_list');
    Cache::forget('channel_plan_map');

    return response()->json([
        'message' => 'Bundle updated successfully',
        'data' => $bundle
    ]);
}

/**
 * Delete a bundle
 */
public function destroy(string $id): JsonResponse
{
    $bundle = ServiceBundle::findOrFail($id);

    if ($bundle->plans()->count() > 0) {
        return response()->json([
            'message' => 'Cannot delete bundle: it is currently attached to one or more subscription plans.'
        ], 400);
    }

    $bundle->delete();

    Cache::forget('channel_plan_map');

    return response()->json(['message' => 'Bundle deleted successfully']);
}

    public function deleteBundle(string $bundleId): JsonResponse
    {
        $bundle = ServiceBundle::findOrFail($bundleId);

        // Warn if bundle is attached to plans
        $planCount = $bundle->plans()->count();
        if ($planCount > 0) {
            return response()->json([
                'message'    => 'Cannot delete bundle: it is attached to ' . $planCount . ' plan(s).',
                'error_code' => 'BUNDLE_HAS_PLANS',
                'plans'      => $bundle->plans()->select('subscription_plans.id', 'name_en')->get()
            ], 400);
        }

        $bundle->delete(); // cascade deletes bundle_items via FK
        $this->clearCaches();

        return response()->json(['message' => 'Bundle deleted']);
    }

    public function syncItems(Request $request, string $bundleId): JsonResponse
    {
        $bundle = ServiceBundle::findOrFail($bundleId);

        $request->validate([
            'items'              => 'required|array|min:1', 
            'items.*.item_id'    => 'required|uuid',
            'items.*.item_type'  => 'required|integer|in:1,2,3',
        ]);

        $tableMap = [1 => 'channels', 2 => 'radio_channels', 3 => 'app_modules'];

        foreach ($request->items as $item) {
            $table = $tableMap[$item['item_type']];
            $exists = DB::table($table)->where('id', $item['item_id'])->exists();
            if (!$exists) {
                return response()->json([
                    'message' => "Item {$item['item_id']} does not exist in {$table}."
                ], 422);
            }
        }

        DB::transaction(function () use ($bundle, $request) {
            $bundle->items()->delete();

            $data = collect($request->items)->map(fn($item) => [
                'bundle_id' => $bundle->id,
                'item_type' => $item['item_type'],
                'item_id'   => $item['item_id'],
            ])->toArray();

            BundleItem::insert($data);
        });

        $this->clearCaches();

        return response()->json(['message' => 'Bundle items synchronized successfully']);
    }

    // Add single item without wiping the whole bundle
    public function addItem(Request $request, string $bundleId): JsonResponse
    {
        $bundle = ServiceBundle::findOrFail($bundleId);

        $request->validate([
            'item_id'   => 'required|uuid',
            'item_type' => 'required|integer|in:1,2,3',
        ]);

        $tableMap = [1 => 'channels', 2 => 'radio_channels', 3 => 'app_modules'];
        $table = $tableMap[$request->item_type];

        if (!DB::table($table)->where('id', $request->item_id)->exists()) {
            return response()->json(['message' => "Item not found in {$table}."], 422);
        }

        BundleItem::updateOrCreate([
            'bundle_id' => $bundle->id,
            'item_type' => $request->item_type,
            'item_id'   => $request->item_id,
        ]);

        $this->clearCaches();

        return response()->json(['message' => 'Item added to bundle']);
    }

    public function removeItem(Request $request, string $bundleId): JsonResponse
    {
        $bundle = ServiceBundle::findOrFail($bundleId);

        $request->validate([
            'item_id'   => 'required|uuid',
            'item_type' => 'required|integer|in:1,2,3',
        ]);

        BundleItem::where('bundle_id', $bundle->id)
            ->where('item_type', $request->item_type)
            ->where('item_id', $request->item_id)
            ->delete();

        $this->clearCaches();

        return response()->json(['message' => 'Item removed from bundle']);
    }

    public function listModules(): JsonResponse
    {
        return response()->json(AppModule::all());
    }

    public function storeModule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug'      => 'required|string|unique:app_modules,slug',
            'name'      => 'required|string',
            'is_active' => 'boolean',
        ]);

        return response()->json(AppModule::create($validated), 201);
    }

    public function toggleModule(string $moduleId): JsonResponse
    {
        $module = AppModule::findOrFail($moduleId);
        $module->update(['is_active' => !$module->is_active]);

        return response()->json(['is_active' => $module->is_active]);
    }

    private function clearCaches(): void
    {
        Cache::forget('global_active_channels_list');
        Cache::forget('channel_plan_map');
    }

}