<?php

use App\Models\User;
use App\Models\Channel;
use App\Models\SubscriptionPlan;
use App\Models\ServiceBundle;
use App\Models\BundleItem;
use App\Models\ChannelUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{actingAs, getJson};

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\SubscriptionPlanSeeder::class]);
    Cache::flush();

    $this->paidPlan = SubscriptionPlan::where('is_default', false)->first();
    $this->bundle = ServiceBundle::create(['slug' => 'premium-tv', 'name' => 'Premium TV', 'type' => 'tv']);
    $this->paidPlan->bundles()->attach($this->bundle->id);

    $this->user = User::create([
        'username' => 'viewer',
        'password' => bcrypt('password'),
    ]);

    // 3. Create a standard Private/Paid Channel
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

    // 4. Seed a stream URL so we can test successful access (not just 403/404)
    ChannelUrl::create([
        'channel_id' => 'private-1',
        'channel_url' => 'http://test.com/stream/s01/test/index.m3u8',
        'url_type' => 3, // Flussonic local
        'priority' => 1
    ]);
});

/**
 * VISIBILITY & ACCESS (getChannelFacade)
 */

it('hides a private channel from unauthorized users', function () {
    actingAs($this->user)
        ->getJson('/api/channels')
        ->assertSuccessful()
        ->assertJsonMissing(['id' => 'private-1']);
});

it('shows a private channel only when the user has the correct plan', function () {
    // Grant Plan
    $this->user->subscriptionPlans()->attach($this->paidPlan->id, [
        'id' => str()->uuid(),
        'started_at' => now(),
        'expires_at' => now()->addMonth(),
        'is_active' => true
    ]);
    Cache::flush();

    actingAs($this->user)
        ->getJson('/api/channels')
        ->assertSuccessful()
        ->assertJson(fn (AssertableJson $json) =>
            $json->has('channels', fn ($items) =>
                $items->where('id', 'private-1')
                      ->where('is_accessible', true)
                      ->etc()
            )->etc()
        );
});

it('shows a public paid channel to guests but marks it as inaccessible', function () {
    $this->privateChannel->update(['is_public' => true]);
    Cache::flush();

    getJson('/api/channels')
        ->assertSuccessful()
        ->assertJson(fn (AssertableJson $json) =>
            $json->has('channels', fn ($items) =>
                $items->where('id', 'private-1')
                      ->where('is_accessible', false)
                      ->etc()
            )->etc()
        );
});

it('shows a free channel to everyone and marks it as accessible', function () {
    Channel::create([
        'external_id' => 'free-ch',
        'name' => 'Free TV',
        'is_public' => true,
        'is_active' => true,
        'is_free' => true,
        'number' => 1
    ]);
    Cache::flush();

    getJson('/api/channels')
        ->assertSuccessful()
        ->assertJson(fn (AssertableJson $json) =>
            $json->has('channels', fn ($items) =>
                $items->where('id', 'free-ch')
                      ->where('is_accessible', true)
                      ->etc()
            )->etc()
        );
});

it('hides a channel entirely if it is inactive, even if public and subscribed', function () {
    $this->privateChannel->update(['is_active' => false, 'is_public' => true]);
    $this->user->subscriptionPlans()->attach($this->paidPlan->id, [
        'id' => str()->uuid(), 'started_at' => now(), 'expires_at' => now()->addMonth(), 'is_active' => true
    ]);
    Cache::flush();

    actingAs($this->user)
        ->getJson('/api/channels')
        ->assertSuccessful()
        ->assertJsonMissing(['id' => 'private-1']);
});

/**
 * STREAM SECURITY (getStreamUrl)
 */

it('denies stream access to paid content for non-subscribers', function () {
    actingAs($this->user)
        ->getJson("/api/channels/private-1/stream")
        ->assertStatus(403);
});

it('allows stream access to paid content for active subscribers', function () {
    $this->user->subscriptionPlans()->attach($this->paidPlan->id, [
        'id' => str()->uuid(), 'started_at' => now(), 'expires_at' => now()->addMonth(), 'is_active' => true
    ]);
    Cache::flush();

    actingAs($this->user)
        ->getJson("/api/channels/private-1/stream")
        ->assertSuccessful()
        ->assertJsonStructure(['url', 'expires_at']);
});

it('denies stream access if the subscription has expired', function () {
    $this->user->subscriptionPlans()->attach($this->paidPlan->id, [
        'id' => str()->uuid(),
        'started_at' => now()->subMonths(2),
        'expires_at' => now()->subMonth(), 
        'is_active' => true
    ]);
    Cache::flush();

    actingAs($this->user)
        ->getJson("/api/channels/private-1/stream")
        ->assertStatus(403);
});

it('allows stream access to a free channel for guest users', function () {
    Channel::create([
        'external_id' => 'free-stream',
        'name' => 'Free Stream',
        'is_free' => true,
        'is_active' => true,
        'number' => 5
    ]);
    ChannelUrl::create([
        'channel_id' => 'free-stream',
        'channel_url' => 'http://test.com/s01/free/index.m3u8',
        'url_type' => 3
    ]);

    getJson("/api/channels/free-stream/stream")
        ->assertSuccessful()
        ->assertJsonStructure(['url']);
});