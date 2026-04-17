<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\ChannelCategory;
use App\Models\SubscriptionPlan;
use App\Services\SyncingService;
class SyncChannelsService
{
    public function __construct(protected SyncingService $syncingService) {}

    public function syncChannels(): int
    {
        $channels = $this->syncingService->fetchChannelList();
        
        if (empty($channels)) {
            return 0;
        }
        $freePlan = SubscriptionPlan::where('name_en', 'Free Package')->first();
        $standardPlan = SubscriptionPlan::where('name_en', 'Standard Package')->first();

        $category = ChannelCategory::firstOrCreate(
            ['name_en' => 'General'],
            [
                'name_ka' => 'სტანდარტული', 
                'description_en' => 'General Channels',
                'description_ka' => 'სტანდარტული არხები'
            ]
        );
        $count = 0;
        foreach ($channels as $remote) {
            $channel = Channel::firstOrCreate(
                ['external_id' => $remote['UID']], 
                [
                    'number' => $remote['CHANNEL_NUMBER'],
                    'name'   => $remote['CHANNEL_NAME'], 
                    'icon_url' => $remote['CHANNEL_LOGO'],
                    'category_id' => $category->id,
                    'is_active' => $remote['STATUS'] == "1",
                    'is_free' => $remote['FREE'] == "1",
                ]
            );
            if ($remote['FREE'] == "1") {
              if ($freePlan) $channel->plans()->syncWithoutDetaching([$freePlan->id]);
            } else {
            if ($standardPlan) $channel->plans()->syncWithoutDetaching([$standardPlan->id]);
        }
            $count++;
        }
        
        return $count;
    }
    public function assignDefaultPlans(Channel $channel, bool $isFree): void
{
    $planName = $isFree ? 'Free Package' : 'Standard Package';
    $plan = SubscriptionPlan::where('name_en', $planName)->first();

    if ($plan) {
        $channel->plans()->syncWithoutDetaching([$plan->id]);
    }
}
}