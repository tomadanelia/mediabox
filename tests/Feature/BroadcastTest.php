<?php

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\Account;
use App\Services\SubscriptionService;
use App\Services\BroadcastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

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