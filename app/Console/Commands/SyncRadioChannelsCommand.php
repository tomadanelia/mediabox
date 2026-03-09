<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RadioSyncService;
use App\Models\RadioChannel;

class SyncRadioChannelsCommand extends Command
{
    protected $signature = 'app:sync-radio';
    protected $description = 'Sync Radio Channels from Legacy API';

    public function handle(RadioSyncService $service)
    {
        $this->info('Syncing Radios...');
        $radios = $service->fetchRadioChannels();

        if (empty($radios)) {
            $this->error('No radio channels found.');
            return;
        }

        foreach ($radios as $item) {
            RadioChannel::updateOrCreate(
                ['external_id' => $item['UID']],
                [
                    'name' => $item['CHANNEL_NAME'],
                    'icon_url' => $item['POSTER'],
                    'is_active' => $item['STATUS'] === true,
                    'is_free' => $item['FREE'] === true,
                ]
            );
        }

        $this->info('Radio channels synced successfully.');
    }
}