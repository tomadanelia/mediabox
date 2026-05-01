<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── TV channels ──────────────────────────────────────────────
        $planIds = DB::table('channel_subscription_plan')
            ->distinct()
            ->pluck('plan_id');

        foreach ($planIds as $planId) {
            $plan = DB::table('subscription_plans')->where('id', $planId)->first();
            if (!$plan) continue;

            $channelIds = DB::table('channel_subscription_plan')
                ->where('plan_id', $planId)
                ->pluck('channel_id');

            if ($channelIds->isEmpty()) continue;   // Bug 2 fix — skip empty plans

            $bundleId = (string) Str::uuid();
            $baseSlug  = 'tv_' . Str::slug($plan->name_en ?: $plan->name_ka);
            $slug      = $this->uniqueSlug($baseSlug);  // Bug 1 fix

            DB::table('service_bundles')->insert([
                'id'         => $bundleId,
                'slug'       => $slug,
                'name'       => ($plan->name_en ?: $plan->name_ka) . ' — TV',
                'type'       => 'tv',
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('plan_services')->insert([
                'plan_id'   => $planId,
                'bundle_id' => $bundleId,
            ]);

            $rows = $channelIds->map(fn($cid) => [
                'bundle_id' => $bundleId,
                'item_type' => 1,
                'item_id'   => $cid,
            ])->all();

            DB::table('bundle_items')->insert($rows);
        }

        // ── Radio channels ───────────────────────────────────────────
        $radioPlanIds = DB::table('radio_subscription_plan')
            ->distinct()
            ->pluck('plan_id');

        foreach ($radioPlanIds as $planId) {
            $plan = DB::table('subscription_plans')->where('id', $planId)->first();
            if (!$plan) continue;

            $radioIds = DB::table('radio_subscription_plan')
                ->where('plan_id', $planId)
                ->pluck('radio_id');

            if ($radioIds->isEmpty()) continue;     // Bug 2 fix

            $bundleId = (string) Str::uuid();
            $baseSlug  = 'radio_' . Str::slug($plan->name_en ?: $plan->name_ka);
            $slug      = $this->uniqueSlug($baseSlug);  // Bug 1 fix

            DB::table('service_bundles')->insert([
                'id'         => $bundleId,
                'slug'       => $slug,
                'name'       => ($plan->name_en ?: $plan->name_ka) . ' — Radio',
                'type'       => 'radio',
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('plan_services')->insert([
                'plan_id'   => $planId,
                'bundle_id' => $bundleId,
            ]);

            $rows = $radioIds->map(fn($rid) => [
                'bundle_id' => $bundleId,
                'item_type' => 2,
                'item_id'   => $rid,
            ])->all();

            DB::table('bundle_items')->insert($rows);
        }

        // ── Free plan default flag ───────────────────────────────────
        DB::table('subscription_plans')
            ->where('id', '00000000-0000-0000-0000-000000000000')
            ->update(['is_default' => true]);
    }

    public function down(): void
    {
        DB::table('bundle_items')->delete();
        DB::table('plan_services')->delete();
        DB::table('service_bundles')->delete();
    }

    // Appends _2, _3 etc. if a slug already exists in this migration run
    private function uniqueSlug(string $base): string
    {
        $slug    = $base;
        $counter = 2;
        while (DB::table('service_bundles')->where('slug', $slug)->exists()) {
            $slug = $base . '_' . $counter++;
        }
        return $slug;
    }
};