<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SyncChannelsService;
class SyncChannelsCommand extends Command
{
    protected $signature = 'app:sync-channels';
    protected $description = 'Sync channels from Legacy API to my DB';

     public function handle(SyncChannelsService $syncService)
    {
        $this->info('Starting Channel Sync...');
        
        $count = $syncService->syncChannels();
        
        if ($count > 0) {
            $this->info("Successfully synced {$count} channels.");
        } else {
            $this->error('No channels synced. Check logs or API connection.');
        }
    }
}