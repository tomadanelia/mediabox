<?php

use App\Models\User;
use App\Models\Channel;
use App\Models\RadioChannel;
use App\Models\SubscriptionPlan;
use App\Models\ServiceBundle;
use App\Models\BundleItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\{actingAs, postJson, deleteJson};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->seed([\Database\Seeders\SubscriptionPlanSeeder::class]);
    
    $this->freePlan = SubscriptionPlan::find('00000000-0000-0000-0000-000000000000');
    $this->paidPlan = SubscriptionPlan::where('is_default', false)->first();

    $this->admin = User::create([
        'username' => 'admin',
        'password' => bcrypt('password'),
        'role' => 'admin',
    ]);

    $this->channel = Channel::create([
        'external_id' => '999',
        'name' => 'Test Channel',
        'number' => 1,
        'is_free' => false
    ]);
    $this->bundleA = ServiceBundle::create(['slug' => 'bundle-a', 'name' => 'Bundle A', 'type' => 'tv']);
    $this->bundleB = ServiceBundle::create(['slug' => 'bundle-b', 'name' => 'Bundle B', 'type' => 'tv']);

    $this->channel = Channel::create([
        'external_id' => '101',
        'name' => 'Test Channel',
        'is_free' => false,
        'number' => 1
    ]);

    $this->radio = RadioChannel::create([
        'external_id' => 1,
        'name' => 'Test Radio',
        'is_free' => false,
    ]);
});

it('prevents attaching a bundle to Free plan if it contains items already in a Paid plan', function () {
    BundleItem::create(['bundle_id' => $this->bundleB->id, 'item_type' => 1, 'item_id' => $this->channel->id]);
    $this->paidPlan->bundles()->attach($this->bundleB->id);

    actingAs($this->admin)
        ->postJson("/api/admin/plans/{$this->freePlan->id}/bundles", [
            'bundle_id' => $this->bundleB->id
        ])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'BUNDLE_ITEMS_IN_PAID_PLANS');
});

/**
 * ATTACH BUNDLE TESTS
 */

it('updates is_free flags when a bundle is attached to the free plan', function () {
    BundleItem::create(['bundle_id' => $this->bundleA->id, 'item_type' => 1, 'item_id' => $this->channel->id]);
    BundleItem::create(['bundle_id' => $this->bundleA->id, 'item_type' => 2, 'item_id' => $this->radio->id]);

    actingAs($this->admin)
        ->postJson("/api/admin/plans/{$this->freePlan->id}/bundles", [
            'bundle_id' => $this->bundleA->id
        ])
        ->assertSuccessful();

    expect($this->channel->fresh()->is_free)->toBeTrue();
    expect($this->radio->fresh()->is_free)->toBeTrue();
});

/**
 * ADD ITEM TESTS
 */

it('sets is_free to true when adding a channel to a bundle already linked to the free plan', function () {
    $this->freePlan->bundles()->attach($this->bundleA->id);

    actingAs($this->admin)
        ->postJson("/api/admin/bundles/{$this->bundleA->id}/items", [
            'item_id' => $this->channel->id,
            'item_type' => 1
        ])
        ->assertSuccessful();

    expect($this->channel->fresh()->is_free)->toBeTrue();
});

it('prevents adding a paid channel to a bundle linked to the free plan', function () {
    BundleItem::create(['bundle_id' => $this->bundleB->id, 'item_type' => 1, 'item_id' => $this->channel->id]);
    $this->paidPlan->bundles()->attach($this->bundleB->id);

    $this->freePlan->bundles()->attach($this->bundleA->id);

    actingAs($this->admin)
        ->postJson("/api/admin/bundles/{$this->bundleA->id}/items", [
            'item_id' => $this->channel->id,
            'item_type' => 1
        ])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'ITEM_IN_PAID_PLAN');
});

it('prevents adding a free channel to a bundle linked to a paid plan', function () {
    BundleItem::create(['bundle_id' => $this->bundleA->id, 'item_type' => 1, 'item_id' => $this->channel->id]);
    $this->freePlan->bundles()->attach($this->bundleA->id);

    $this->paidPlan->bundles()->attach($this->bundleB->id);

    actingAs($this->admin)
        ->postJson("/api/admin/bundles/{$this->bundleB->id}/items", [
            'item_id' => $this->channel->id,
            'item_type' => 1
        ])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'ITEM_IN_FREE_PLAN');
});



it('successfully detaches a bundle from a plan', function () {
    $this->paidPlan->bundles()->attach($this->bundleA->id);

    actingAs($this->admin)
        ->deleteJson("/api/admin/plans/{$this->paidPlan->id}/bundles", [
            'bundle_id' => $this->bundleA->id
        ])
        ->assertSuccessful();

    expect(DB::table('plan_services')->count())->toBe(0);
});

it('successfully removes an item from a bundle', function () {
    BundleItem::create(['bundle_id' => $this->bundleA->id, 'item_type' => 1, 'item_id' => $this->channel->id]);

    actingAs($this->admin)
        ->deleteJson("/api/admin/bundles/{$this->bundleA->id}/items", [
            'item_id' => $this->channel->id,
            'item_type' => 1
        ])
        ->assertSuccessful();

    expect(BundleItem::count())->toBe(0);
});
/**
 * IS_FREE RESET LOGIC TESTS
 */

it('sets is_free to false when a bundle is detached from the Free plan', function () {
    BundleItem::create(['bundle_id' => $this->bundleA->id, 'item_type' => 1, 'item_id' => $this->channel->id]);
    $this->freePlan->bundles()->attach($this->bundleA->id);
    
    $this->channel->update(['is_free' => true]);

    actingAs($this->admin)
        ->deleteJson("/api/admin/plans/{$this->freePlan->id}/bundles", [
            'bundle_id' => $this->bundleA->id
        ])
        ->assertSuccessful();

    expect($this->channel->fresh()->is_free)->toBeFalse();
});

it('sets is_free to false when an item is removed from a Free bundle', function () {
    $this->freePlan->bundles()->attach($this->bundleA->id);
    BundleItem::create(['bundle_id' => $this->bundleA->id, 'item_type' => 1, 'item_id' => $this->channel->id]);
    $this->channel->update(['is_free' => true]);

    actingAs($this->admin)
        ->deleteJson("/api/admin/bundles/{$this->bundleA->id}/items", [
            'item_id' => $this->channel->id,
            'item_type' => 1
        ])
        ->assertSuccessful();

    expect($this->channel->fresh()->is_free)->toBeFalse();
});

it('keeps is_free as true if the item exists in another Free bundle', function () {
    $this->freePlan->bundles()->attach($this->bundleA->id);
    $this->freePlan->bundles()->attach($this->bundleB->id);
    
    BundleItem::create(['bundle_id' => $this->bundleA->id, 'item_type' => 1, 'item_id' => $this->channel->id]);
    BundleItem::create(['bundle_id' => $this->bundleB->id, 'item_type' => 1, 'item_id' => $this->channel->id]);
    
    $this->channel->update(['is_free' => true]);

    actingAs($this->admin)
        ->deleteJson("/api/admin/bundles/{$this->bundleA->id}/items", [
            'item_id' => $this->channel->id,
            'item_type' => 1
        ])
        ->assertSuccessful();

    expect($this->channel->fresh()->is_free)->toBeTrue();

    actingAs($this->admin)
        ->deleteJson("/api/admin/plans/{$this->freePlan->id}/bundles", [
            'bundle_id' => $this->bundleB->id
        ])
        ->assertSuccessful();

    expect($this->channel->fresh()->is_free)->toBeFalse();
});

it('handles is_free for RadioChannels identically to TV Channels', function () {
    $this->freePlan->bundles()->attach($this->bundleA->id);
    BundleItem::create(['bundle_id' => $this->bundleA->id, 'item_type' => 2, 'item_id' => $this->radio->id]);
    $this->radio->update(['is_free' => true]);

    actingAs($this->admin)
        ->deleteJson("/api/admin/plans/{$this->freePlan->id}/bundles", [
            'bundle_id' => $this->bundleA->id
        ])
        ->assertSuccessful();

    expect($this->radio->fresh()->is_free)->toBeFalse();
});