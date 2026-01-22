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
            Channel::firstOrCreate(
                ['external_id' => $remote['UID']], 
                [
                    'number' => $remote['CHANNEL_NUMBER'],
                    'name_ka' => $remote['CHANNEL_NAME'],
                    'name_en' => $remote['CHANNEL_NAME'], 
                    'icon_url' => $remote['CHANNEL_LOGO'],
                    'category_id' => $category->id,
                    'is_active' => $remote['STATUS'] == "1",
                    'access_level' => $remote['FREE'] == "1" ? "free" : "premium", 
                ]
            );
            $count++;
        }

        $this->info("Synced {$count} channels successfully.");
    }
}