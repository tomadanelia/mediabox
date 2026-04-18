<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Channel;
use App\Models\ChannelUrl;
use App\Models\ChannelArchiveUrl;
use App\Services\SyncChannelsService;

class MigrateLegacyData extends Command
{
    protected $signature = 'app:migrate-legacy';
    protected $description = 'Transform data from legacy tables to new structure';

    public function handle(SyncChannelsService $syncService)
    {
        $filePath = database_path('legacy_dump.sql');
        $imageBaseUrl = 'https://img.mediabox.ge/';
        if (File::exists($filePath)) {
            $this->info('Importing raw SQL dump into database...');
            DB::unprepared(File::get($filePath));
        }

        $this->info('Starting legacy data transformation...');

        $legacyChannels = DB::table('channel_list')->get();
        $this->info("Processing {$legacyChannels->count()} channels...");

        foreach ($legacyChannels as $item) {
             $fullLogoUrl = !empty($item->CHANNEL_LOGO) 
                ? $imageBaseUrl . $item->CHANNEL_LOGO 
                : null;
            $channel = Channel::updateOrCreate(
                ['external_id' => (string) $item->UID],
                [
                    'name'        => $item->CHANNEL_NAME,
                    'number'      => (int) $item->CHANNEL_NUMBER,
                    'icon_url'    => $fullLogoUrl,
                    'is_active'   => (bool) $item->STATUS,
                    'is_free'     => (bool) $item->FREE,
            ]
            );

            $syncService->assignDefaultPlans($channel, (bool) $item->FREE);
        }

        $this->info('Migrating Live Stream URLs...');
        $legacyUrls = DB::table('legacy_channel_urls')->where('CHANNEL_URL', '!=', '')->get();

        foreach ($legacyUrls as $item) {
            $cleanUrl = str_replace('&amp;', '&', $item->CHANNEL_URL);

            ChannelUrl::updateOrCreate(
                ['id' => $item->UID],
                [
                    'channel_id'  => (string) $item->CHANNEL_ID,
                    'channel_url' => $cleanUrl,
                    'url_type'    => (int) $item->URL_TYPE,
                    'filter'      => $item->FILTER,
                    'priority'    => (int) $item->PRIORITY,
                ]
            );
        }

        $this->info('Migrating Archive URLs...');
        $legacyArchives = DB::table('legacy_channel_archive_urls')->where('CHANNEL_URL', '!=', '')->get();

        foreach ($legacyArchives as $item) {
            $cleanUrl = str_replace('&amp;', '&', $item->CHANNEL_URL);

            ChannelArchiveUrl::updateOrCreate(
                ['id' => $item->UID],
                [
                    'channel_id'     => (string) $item->CHANNEL_ID,
                    'channel_url'    => $cleanUrl,
                    'url_type'       => (int) $item->URL_TYPE,
                    'archive_length' => (int) $item->ARCHIVE_LENGTH,
                    'priority'       => (int) $item->PRIORITY,
                ]
            );
        }

        $this->info('Cleaning up temporary legacy tables...');
        DB::statement('DROP TABLE IF EXISTS channel_list');
        DB::statement('DROP TABLE IF EXISTS legacy_channel_urls');
        DB::statement('DROP TABLE IF EXISTS legacy_channel_archive_urls');

        $this->info('Migration complete! All channels are now live with local tokens.');
    }
}