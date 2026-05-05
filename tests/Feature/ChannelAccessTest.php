<?php

use App\Models\User;
use App\Models\Channel;
use App\Models\SubscriptionPlan;
use App\Models\ServiceBundle;
use App\Models\BundleItem;
use Illuminate\Support\Facades\Cache;
use function Pest\Laravel\{actingAs, getJson};

beforeEach(function () {
    $this->seed([\Database\Seeders\SubscriptionPlanSeeder::class]);
    
    $this->paidPlan = SubscriptionPlan::where('is_default', false)->first();
    $this->bundle = ServiceBundle::create(['slug' => 'premium-tv', 'name' => 'Premium TV', 'type' => 'tv']);
    $this->paidPlan->bundles()->attach($this->bundle->id);

    $this->user = User::create([
        'username' => 'viewer',
        'password' => bcrypt('password'),
    ]);

    $this->privateChannel = Channel::create([
        'external_id' => 'private-1',
        'name' => 'Private Channel',
        'is_public' => false,
        'is_active' => true,
        'is_free' => false,
        'number' => 10
    ]);

    BundleItem::create([
        'bundle_id' => $this->bundle->id,
        'item_type' => 1,
        'item_id' => $this->privateChannel->id
    ]);

    Cache::forget('global_active_channels_list');
    Cache::forget('channel_plan_map');
});

/**
 * VISIBILITY TESTS (getChannelFacade)
 */

it('hides a private channel from a user without the correct plan', function () {
    actingAs($this->user)
        ->getJson('/api/channels')
        ->assertSuccessful()
        ->assertJsonMissing(['id' => 'private-1']);
});

it('shows a private channel to a user who has the correct plan', function () {
    $this->user->subscriptionPlans()->attach($this->paidPlan->id, [
        'id' => str()->uuid(),
        'started_at' => now(),
        'expires_at' => now()->addMonth(),
        'is_active' => true
    ]);
    Cache::forget("user_plan_ids_{$this->user->id}");

    actingAs($this->user)
        ->getJson('/api/channels')
        ->assertSuccessful()
        ->assertJsonFragment([
            'id' => 'private-1',
            'is_accessible' => true
        ]);
});

it('hides a channel entirely if it is inactive, even if the user has the plan', function () {
    $this->user->subscriptionPlans()->attach($this->paidPlan->id, [
        'id' => str()->uuid(),
        'started_at' => now(),
        'expires_at' => now()->addMonth(),
        'is_active' => true
    ]);
    
    $this->privateChannel->update(['is_active' => false]);
    Cache::forget('global_active_channels_list');

    actingAs($this->user)
        ->getJson('/api/channels')
        ->assertSuccessful()
        ->assertJsonMissing(['id' => 'private-1']);
});

it('shows a public paid channel to everyone but marks is_accessible false for non-subscribers', function () {
    $this->privateChannel->update(['is_public' => true]);
    Cache::forget('global_active_channels_list');

    getJson('/api/channels')
        ->assertSuccessful()
        ->assertJsonFragment([
            'id' => 'private-1',
            'is_accessible' => false
        ]);
});

/**
 * STREAM ACCESS TESTS (getStreamUrl)
 */

it('prevents stream access to a paid channel if user is not subscribed', function () {
    actingAs($this->user)
        ->getJson("/api/channels/private-1/stream")
        ->assertStatus(403); 
});

it('allows stream access to a paid channel if user has an active subscription', function () {
    $this->user->subscriptionPlans()->attach($this->paidPlan->id, [
        'id' => str()->uuid(),
        'started_at' => now(),
        'expires_at' => now()->addMonth(),
        'is_active' => true
    ]);
    Cache::forget("user_plan_ids_{$this->user->id}");

    $response = actingAs($this->user)->getJson("/api/channels/private-1/stream");
    
    expect($response->status())->not->toBe(403);
});

it('denies stream access if the subscription has expired', function () {
    $this->user->subscriptionPlans()->attach($this->paidPlan->id, [
        'id' => str()->uuid(),
        'started_at' => now()->subMonths(2),
        'expires_at' => now()->subMonth(), // Expired 1 month ago
        'is_active' => true
    ]);
    Cache::forget("user_plan_ids_{$this->user->id}");

    actingAs($this->user)
        ->getJson("/api/channels/private-1/stream")
        ->assertStatus(403);
});