<?php

use App\Models\User;
use App\Models\Channel;
use App\Models\SubscriptionPlan;
use App\Models\ServiceBundle;
use App\Models\BundleItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{actingAs, getJson, withSession};

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\SubscriptionPlanSeeder::class]);
    Cache::flush();

    $this->freePlanId = '00000000-0000-0000-0000-000000000000';
    $this->freePlan = SubscriptionPlan::find($this->freePlanId);
    
    // Create a Free Channel
    $this->freeChannel = Channel::create([
        'external_id' => 'free-1', 'name' => 'Free Channel', 'is_active' => true, 'is_public' => true, 'number' => 1
    ]);
    $freeBundle = ServiceBundle::create(['slug' => 'free-b', 'name' => 'Free Bundle', 'type' => 'tv']);
    $this->freePlan->bundles()->attach($freeBundle->id);
    BundleItem::create(['bundle_id' => $freeBundle->id, 'item_type' => 1, 'item_id' => $this->freeChannel->id]);

    // Create a Paid Channel
    $this->paidPlan = SubscriptionPlan::where('is_default', false)->where('id', '!=', $this->freePlanId)->first();
    $this->paidChannel = Channel::create([
        'external_id' => 'paid-1', 'name' => 'Paid Channel', 'is_active' => true, 'is_public' => true, 'number' => 2
    ]);
    $paidBundle = ServiceBundle::create(['slug' => 'paid-b', 'name' => 'Paid Bundle', 'type' => 'tv']);
    $this->paidPlan->bundles()->attach($paidBundle->id);
    BundleItem::create(['bundle_id' => $paidBundle->id, 'item_type' => 1, 'item_id' => $this->paidChannel->id]);

    $this->user = User::create(['username' => 'viewer', 'password' => bcrypt('password')]);
});

/**
 * GUEST ACCESS (Handshake Tests)
 */

it('denies free channel access to guest if handshake was not performed', function () {
    // No session variable 'is_web_visitor' set
    getJson("/api/channels/{$this->freeChannel->external_id}/stream")
        ->assertStatus(403);
});

it('allows free channel access to guest if handshake was performed', function () {
    // withSession simulates the result of /api/init-visitor
    withSession(['is_web_visitor' => true])
        ->getJson("/api/channels/{$this->freeChannel->external_id}/stream")
        ->assertSuccessful();
});

/**
 * AUTHENTICATED ACCESS (APK / Logged in)
 */

it('allows free channel access to logged in users without a handshake', function () {
    // Logged in users (like APK users) bypass the session check
    actingAs($this->user)
        ->getJson("/api/channels/{$this->freeChannel->external_id}/stream")
        ->assertSuccessful();
});

it('denies paid channel access to logged in users without the correct plan', function () {
    actingAs($this->user)
        ->getJson("/api/channels/{$this->paidChannel->external_id}/stream")
        ->assertStatus(403);
});

it('allows paid channel access to users with the correct plan', function () {
    // Give user the paid plan
    $this->user->subscriptionPlans()->attach($this->paidPlan->id, [
        'id' => str()->uuid(), 'started_at' => now(), 'expires_at' => now()->addMonth(), 'is_active' => true
    ]);
    Cache::flush();

    actingAs($this->user)
        ->getJson("/api/channels/{$this->paidChannel->external_id}/stream")
        ->assertSuccessful();
});

/**
 * ORPHANED CHANNELS
 */

it('denies access to a channel that belongs to NO bundle/plan', function () {
    $orphaned = Channel::create(['external_id' => 'orphan', 'name' => 'Orphan', 'is_active' => true, 'number' => 99]);
    
    // Even with handshake, guest can't see it because it has no plan assigned
    withSession(['is_web_visitor' => true])
        ->getJson("/api/channels/orphan/stream")
        ->assertStatus(403);

    // Even admin should technically be blocked if your logic is "No Plan = No Access" 
    // (though usually we let admins through, your logic check was strict)
    actingAs($this->user)
        ->getJson("/api/channels/orphan/stream")
        ->assertStatus(403);
});