<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Channel;

class ChannelSeeder extends Seeder
{

    public function run(): void
    {
            $oldData = [
            "URL" => "https://proxy2.streamer.mediabox.ge/s01/8080/tv/pirveli_arkhi/index.m3u8?token=caa4e64f62becd697cafb4451223fc02d1f14660-da2ca4953aacf8e6d6f49f2368f2cfde-1766108718-1766094018",
            "END" => "1766108718",
            "CHANNEL" => "pirveli arkhi",
            "SERVER" => "https://proxy2.streamer.mediabox.ge/s01/8080",
            "TOKEN" => "caa4e64f62becd697cafb4451223fc02d1f14660-da2ca4953aacf8e6d6f49f2368f2cfde-1766108718-1766094018",
            "FILE" => "/index.m3u8"
        ];

        $name = basename($oldData['CHANNEL']); 

        Channel::firstOrCreate(
            ['stream_url' => $oldData['URL']],
            [
            'id' => null,
            'name_en' => $name,
            'name_ka' => "პირველი არხი",
            'description_ka' => null,
            'description_en' => null,
            'thumbnail_url' => null,
            'icon_url' => "resources\icons\channels\pirveli_arkhi_logo.svg",
            'category_id' => "4236cb6d-262f-4d42-9fe7-85c98bfc7d99",
            'is_active' => true,
            'is_vip_only' => false,
            'view_count' => 0,
        ]);
    }
}
