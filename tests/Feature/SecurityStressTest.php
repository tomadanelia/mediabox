<?php

use App\Models\User;
use App\Models\Channel;
use App\Models\SubscriptionPlan;
use App\Models\ServiceBundle;
use App\Models\BundleItem;
use App\Services\SyncingService;
use Mockery\MockInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{actingAs, getJson, withSession};

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\SubscriptionPlanSeeder::class]);
    Cache::flush();
    
    // Mock the streamer so we don't get 404s from the external API
    $this->mock(SyncingService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStreamUrl')->andReturn(['url' => 'http://secure.stream']);
    });

    $this->freePlanId = '00000000-0000-0000-0000-000000000000';
});

/**
 * THE "ZOMBIE" TEST
 */
it('denies access to a channel in a bundle that is NOT linked to any plan', function () {
    // 1. Setup: Channel exists and has the legacy is_free flag set to true
    $channel = Channel::create([
        'external_id' => 'zombie-ch',
        'name' => 'Zombie Channel',
        'is_free' => true, // Legacy flag is TRUE
        'is_active' => true,
        'number' => 99
    ]);

    // 2. Setup: Bundle exists and channel is inside it
    $bundle = ServiceBundle::create(['slug' => 'orphaned-bundle', 'name' => 'No Plan Bundle', 'type' => 'tv']);
    BundleItem::create([
        'bundle_id' => $bundle->id,
        'item_type' => 1,
        'item_id'   => $channel->id
    ]);

    // 3. CRITICAL: We DO NOT attach this bundle to the Free Plan or any Paid Plan.

    // 4. Action: Legitimate user with handshake tries to watch
    withSession(['is_web_visitor' => true])
        ->getJson("/api/channels/zombie-ch/stream")
        ->assertStatus(403); // MUST be denied because requiredPlanIds will be empty

    // 5. Action: Even an Admin should be denied if the logic is "No Plan = No Access"
    $admin = User::create(['username' => 'admin', 'role' => 'admin', 'password' => 'pass']);
    actingAs($admin)
        ->getJson("/api/channels/zombie-ch/stream")
        ->assertStatus(403);
});
it('denies access if the plan containing the bundle is set to inactive', function () {
    $channel = Channel::create(['external_id' => 'test-ch', 'name' => 'Test', 'is_active' => true, 'number' => 5]);
    $bundle = ServiceBundle::create(['slug' => 'test-b', 'name' => 'Test B', 'type' => 'tv']);
    BundleItem::create(['bundle_id' => $bundle->id, 'item_type' => 1, 'item_id' => $channel->id]);
    
    $plan = SubscriptionPlan::create(['name_ka' => 'Plan', 'name_en' => 'Plan', 'price' => 10, 'is_active' => false]); // INACTIVE
    $plan->bundles()->attach($bundle->id);

    $user = User::create(['username' => 'user', 'password' => 'pass']);
    $user->subscriptionPlans()->attach($plan->id, ['id' => str()->uuid(), 'is_active' => true, 'expires_at' => now()->addDays(10)]);

    Cache::flush();

    actingAs($user)
        ->getJson("/api/channels/test-ch/stream")
        ->assertStatus(403); // Denied because SubscriptionPlan::is_active is false
});
it('allows access to a free channel even if the users specific paid subscription for it has expired', function () {
    $channel = Channel::create(['external_id' => 'shared-ch', 'name' => 'Shared', 'is_active' => true, 'number' => 5]);
    
    // Put in Paid Plan
    $paidPlan = SubscriptionPlan::create(['name_en' => 'Premium', 'price' => 20, 'is_active' => true]);
    $paidBundle = ServiceBundle::create(['slug' => 'premium-b', 'name' => 'Premium B', 'type' => 'tv']);
    $paidPlan->bundles()->attach($paidBundle->id);
    BundleItem::create(['bundle_id' => $paidBundle->id, 'item_type' => 1, 'item_id' => $channel->id]);

    // ALSO put in Free Plan
    $freeBundle = ServiceBundle::create(['slug' => 'free-b', 'name' => 'Free B', 'type' => 'tv']);
    SubscriptionPlan::find($this->freePlanId)->bundles()->attach($freeBundle->id);
    BundleItem::create(['bundle_id' => $freeBundle->id, 'item_type' => 1, 'item_id' => $channel->id]);

    $user = User::create(['username' => 'user', 'password' => 'pass']);
    // Give user the Paid Plan but make it EXPIRED
    $user->subscriptionPlans()->attach($paidPlan->id, [
        'id' => str()->uuid(), 
        'is_active' => true, 
        'expires_at' => now()->subDay() // EXPIRED
    ]);

    Cache::flush();

    // Should still work because the channel is currently in the Free Plan
    actingAs($user)
        ->getJson("/api/channels/shared-ch/stream")
        ->assertSuccessful();
});
it('denies access to guests who try to spoof being an app without a token', function () {
    $channel = Channel::create(['external_id' => 'paid-only', 'name' => 'Paid', 'is_active' => true, 'number' => 1]);
    // Linked to a paid plan...

    getJson("/api/channels/paid-only/stream", [
        'User-Agent' => 'MediaBox-TV-APK', // Spoofed header
        'Accept' => 'application/json'
    ])->assertStatus(403);
});