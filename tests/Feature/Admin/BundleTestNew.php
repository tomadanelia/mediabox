<?php

use App\Models\User;
use App\Models\Channel;
use App\Models\SubscriptionPlan;
use App\Models\ServiceBundle;
use App\Models\BundleItem;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\{actingAs};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\SubscriptionPlanSeeder::class]);
    
    $this->freePlan = SubscriptionPlan::find('00000000-0000-0000-0000-000000000000');
    $this->paidPlan = SubscriptionPlan::where('is_default', false)->where('id', '!=', $this->freePlan->id)->first();

    $this->admin = User::create(['username' => 'admin', 'password' => bcrypt('password'), 'role' => 'admin']);

    $this->channel = Channel::create(['external_id' => '101', 'name' => 'Test Channel', 'number' => 1]);
    $this->bundleA = ServiceBundle::create(['slug' => 'bundle-a', 'name' => 'Bundle A', 'type' => 'tv']);
});

it('prevents attaching a bundle to Free plan if it contains items already in a Paid plan', function () {
    // Put channel in Bundle A, and link Bundle A to a Paid Plan
    BundleItem::create(['bundle_id' => $this->bundleA->id, 'item_type' => 1, 'item_id' => $this->channel->id]);
    $this->paidPlan->bundles()->attach($this->bundleA->id);

    // Try to link the same Bundle A to the Free Plan
    actingAs($this->admin)
        ->postJson("/api/admin/plans/{$this->freePlan->id}/bundles", [
            'bundle_id' => $this->bundleA->id
        ])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'BUNDLE_ITEMS_IN_PAID_PLANS');
});

it('prevents adding a paid channel to a bundle already linked to the free plan', function () {
    // Channel is already in a paid plan
    $paidBundle = ServiceBundle::create(['slug' => 'paid-b', 'name' => 'Paid B', 'type' => 'tv']);
    BundleItem::create(['bundle_id' => $paidBundle->id, 'item_type' => 1, 'item_id' => $this->channel->id]);
    $this->paidPlan->bundles()->attach($paidBundle->id);

    // Bundle A is linked to Free Plan
    $this->freePlan->bundles()->attach($this->bundleA->id);

    // Try to add that paid channel into the Free Bundle A
    actingAs($this->admin)
        ->postJson("/api/admin/bundles/{$this->bundleA->id}/items", [
            'item_id' => $this->channel->id,
            'item_type' => 1
        ])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'ITEM_IN_PAID_PLAN');
});