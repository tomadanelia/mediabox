<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Channel;
use App\Models\ChannelUrl;
use App\Models\ChannelArchiveUrl;
use App\Models\ChannelCategory;
use App\Models\SubscriptionPlan;
use App\Services\SyncingService;

class SyncChannelsService
{
    public function __construct(protected SyncingService $syncingService) {}
    public function migrateFromDump(SyncChannelsService $syncService): array
    {
        $filePath = database_path('legacy_dump.sql');
        $imageBaseUrl = 'https://img.mediabox.ge/';
        if (File::exists($filePath)) {
            DB::unprepared(File::get($filePath));
        }


        $legacyChannels = DB::table('channel_list')->get();

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

        DB::statement('DROP TABLE IF EXISTS channel_list');
        DB::statement('DROP TABLE IF EXISTS legacy_channel_urls');
        DB::statement('DROP TABLE IF EXISTS legacy_channel_archive_urls');
        return [
        'channels' => $legacyChannels->count(),
        'urls'     => $legacyUrls->count(),
        'archives' => $legacyArchives->count(),
    ];
    }
    //i dont use this method anymore, but i want to keep it for reference
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