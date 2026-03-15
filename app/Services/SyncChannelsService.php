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

        $category = ChannelCategory::firstOrCreate(
            ['name_en' => 'General'],
            [
                'name_ka' => 'სტანდარტული', 
                'description_en' => 'General Channels',
                'description_ka' => 'სტანდარტული არხები'
            ]
        );
        $defaultPlan = SubscriptionPlan::where('name_en', 'Standard Package')->first();
        $count = 0;
        foreach ($channels as $remote) {
            $channel = Channel::firstOrCreate(
                ['external_id' => $remote['UID']], 
                [
                    'number' => $remote['CHANNEL_NUMBER'],
                    'name_ka' => $remote['CHANNEL_NAME'],
                    'name_en' => $remote['CHANNEL_NAME'], 
                    'icon_url' => $remote['CHANNEL_LOGO'],
                    'category_id' => $category->id,
                    'is_active' => $remote['STATUS'] == "1",
                    'is_free' => $remote['FREE'] == "1",
                ]
            );

            if ($channel->wasRecentlyCreated && $channel->is_free === false) {
                if ($defaultPlan) {
                    $channel->plans()->syncWithoutDetaching([$defaultPlan->id]);
                }
            }
            $count++;
        }

        return $count;
    }
}