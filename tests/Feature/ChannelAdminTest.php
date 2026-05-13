<?php

use App\Models\User;
use App\Models\Channel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{actingAs, patchJson};

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::create([
        'username' => 'admin',
        'password' => bcrypt('password'),
        'role' => 'admin',
    ]);

    Channel::create(['external_id' => 'ch1', 'name' => 'Channel 1', 'number' => 1]);
    Channel::create(['external_id' => 'ch2', 'name' => 'Channel 2', 'number' => 2]);
    $this->target = Channel::create(['external_id' => 'ch3', 'name' => 'Channel 3', 'number' => 3]);
    Channel::create(['external_id' => 'ch4', 'name' => 'Channel 4', 'number' => 4]);
    Channel::create(['external_id' => 'ch5', 'name' => 'Channel 5', 'number' => 5]);
});

it('shifts channels UP when moving a channel to a lower number', function () {
    // Move Channel 3 from #3 to #1
    actingAs($this->admin)
        ->patchJson("/api/admin/channels/{$this->target->id}/number", ['number' => 1])
        ->assertSuccessful();

    // Verification:
    // Ch3 should be #1
    // Ch1 should have shifted to #2
    // Ch2 should have shifted to #3
    // Ch4 and Ch5 should remain #4 and #5
    expect(Channel::where('external_id', 'ch3')->first()->number)->toBe(1);
    expect(Channel::where('external_id', 'ch1')->first()->number)->toBe(2);
    expect(Channel::where('external_id', 'ch2')->first()->number)->toBe(3);
    expect(Channel::where('external_id', 'ch4')->first()->number)->toBe(4);
});

it('shifts channels DOWN when moving a channel to a higher number', function () {
    // Move Channel 3 from #3 to #5
    actingAs($this->admin)
        ->patchJson("/api/admin/channels/{$this->target->id}/number", ['number' => 5])
        ->assertSuccessful();

    // Verification:
    // Ch3 should be #5
    // Ch1 and Ch2 should stay #1 and #2
    // Ch4 should have shifted to #3
    // Ch5 should have shifted to #4
    expect(Channel::where('external_id', 'ch3')->first()->number)->toBe(5);
    expect(Channel::where('external_id', 'ch4')->first()->number)->toBe(3);
    expect(Channel::where('external_id', 'ch5')->first()->number)->toBe(4);
});

it('clears relevant caches after reordering', function () {
    Cache::shouldReceive('forget')->with('global_active_channels_list')->once();
    Cache::shouldReceive('forget')->with('channel_plan_map')->once();

    actingAs($this->admin)
        ->patchJson("/api/admin/channels/{$this->target->id}/number", ['number' => 1]);
});

it('returns 422 if number is less than 1', function () {
    actingAs($this->admin)
        ->patchJson("/api/admin/channels/{$this->target->id}/number", ['number' => 0])
        ->assertStatus(422);
});

it('returns message if number is unchanged', function () {
    actingAs($this->admin)
        ->patchJson("/api/admin/channels/{$this->target->id}/number", ['number' => 3])
        ->assertSuccessful()
        ->assertJsonPath('message', 'არხის ნომერი უცვლელია');
});