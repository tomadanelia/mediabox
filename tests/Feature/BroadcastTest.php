<?php

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\Account;
use App\Services\SubscriptionService;
use App\Services\BroadcastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use function Pest\Laravel\{actingAs, postJson};

uses(RefreshDatabase::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::create(['username' => 'admin', 'role' => 'admin', 'password' => 'pass']);
    $this->targetUser = User::create(['username' => 'client', 'password' => 'pass']);
    
    $this->plan = SubscriptionPlan::create([
        'name_ka' => 'პრემიუმი',
        'name_en' => 'Premium',
        'price' => 20.00,
        'duration_days' => 30,
        'is_active' => true
    ]);
});

/**
 * GRANT PLAN TEST
 */
it('broadcasts a notification when admin manually GRANTS a plan', function () {
    // 1. Mock the BroadcastService
    $this->mock(BroadcastService::class, function (MockInterface $mock) {
        $mock->shouldReceive('sendUserNotify')
            ->once()
            ->with(
                $this->targetUser->id,
                'notification_received',
                Mockery::on(function ($data) {
                    return $data['type'] === 'subscription_updated' &&
                           $data['title'] === 'Plan Activated' &&
                           str_contains($data['message'], $this->plan->name_ka);
                })
            );
    });

    // 2. Perform Admin Action
    actingAs($this->admin)
        ->postJson("/api/admin/users/{$this->targetUser->id}/grant-plan", [
            'plan_id' => $this->plan->id,
            'days' => 30
        ])
        ->assertSuccessful();
});

/**
 * REVOKE PLAN TEST
 */
it('broadcasts a notification when admin manually REVOKES a plan', function () {
    // 1. Give the user a plan first
    $this->targetUser->subscriptionPlans()->attach($this->plan->id, [
        'id' => str()->uuid(),
        'expires_at' => now()->addDays(30),
        'is_active' => true
    ]);

    // 2. Mock the BroadcastService
    $this->mock(BroadcastService::class, function (MockInterface $mock) {
        $mock->shouldReceive('sendUserNotify')
            ->once()
            ->with(
                $this->targetUser->id,
                'notification_received',
                Mockery::on(function ($data) {
                    // Check for the "Deactivated" status sent by the controller
                    return $data['type'] === 'subscription_updated' &&
                           $data['title'] === 'Plan Deactivated';
                })
            );
    });

    // 3. Perform Admin Action
    actingAs($this->admin)
        ->postJson("/api/admin/users/{$this->targetUser->id}/revoke-plan", [
            'plan_id' => $this->plan->id
        ])
        ->assertSuccessful();
});
it('broadcasts a websocket notification when a plan is purchased', function () {
    // 1. Setup User and Account with balance
    $user = User::create([
        'username' => 'testuser',
        'numeric_id' => 12345,
        'password' => bcrypt('password')
    ]);
    Account::create(['user_id' => $user->id, 'balance' => 100]);

    // 2. Setup a Plan
    $plan = SubscriptionPlan::create([
        'name_ka' => 'ტესტ პაკეტი',
        'name_en' => 'Test Plan',
        'price' => 10.00,
        'duration_days' => 30,
        'is_active' => true
    ]);

    // 3. MOCK the BroadcastService
    // We want to catch the call and inspect the arguments
    $this->mock(BroadcastService::class, function (MockInterface $mock) use ($user, $plan) {
        $mock->shouldReceive('sendUserNotify')
            ->once() // It must be called exactly once
            ->with(
                $user->id, 
                'notification_received', // The event name
                Mockery::on(function ($data) use ($plan) {
                    // Check if the payload structure matches what Node.js expects
                    return $data['type'] === 'subscription_updated' &&
                           $data['payload']['plan_id'] === $plan->id &&
                           str_contains($data['message'], $plan->name_ka);
                })
            );
    });

    // 4. Execute the purchase via the service
    $service = app(SubscriptionService::class);
    $service->purchasePlan($user, $plan->id, false);
});

it('broadcasts a websocket notification when TV limit is upgraded', function () {
    $user = User::create(['username' => 'testuser2', 'numeric_id' => 54321, 'password' => 'pass']);
    Account::create(['user_id' => $user->id, 'balance' => 100]);

    $this->mock(BroadcastService::class, function (MockInterface $mock) use ($user) {
        $mock->shouldReceive('sendUserNotify')
            ->once()
            ->with(
                $user->id,
                'notification_received',
                Mockery::on(function ($data) {
                    return $data['type'] === 'tv_limit_updated' && 
                           isset($data['new_limit']);
                })
            );
    });

    $service = app(SubscriptionService::class);
    $service->purchaseTvLimitUpgrade($user, 1);
});