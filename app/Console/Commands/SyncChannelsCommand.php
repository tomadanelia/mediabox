<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SyncingService;
use App\Models\Channel;
use App\Models\ChannelCategory;

class SyncChannelsCommand extends Command
{
    protected $signature = 'app:sync-channels';
    protected $description = 'Sync channels from Legacy API to my DB';

    public function handle(SyncingService $service)
    {
        $this->info('Fetching channels from MediaBox222...');
        
        $channels = $service->fetchChannelList();
        
        if (empty($channels)) {
            $this->error('No channels received.');
            return;
        }

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
                    'name_ka' => $remote['CHANNEL_NAME'],
                    'name_en' => $remote['CHANNEL_NAME'], 
                    'icon_url' => $remote['CHANNEL_LOGO'],
                    'category_id' => $category->id,
                    'is_active' => $remote['STATUS'] == "1",
                    'is_free' => $remote['FREE'] == "1",
                ]
            );
         if ($channel->is_free === false) {
        $defaultPlan = SubscriptionPlan::where('name_en', 'Standard Package')->first();
        if ($defaultPlan) {
            $channel->plans()->syncWithoutDetaching([$defaultPlan->id]);
        }
       }
        $count++;
     }

        $this->info("Synced {$count} channels successfully.");
    }
}