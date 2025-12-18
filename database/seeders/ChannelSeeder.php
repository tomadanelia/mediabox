<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Channel;

class ChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
            $oldData = [
            "URL" => "https://proxy2.streamer.mediabox.ge/s01/8080/tv/pirveli_arkhi/index.m3u8?token=caa4e64f62becd697cafb4451223fc02d1f14660-da2ca4953aacf8e6d6f49f2368f2cfde-1766108718-1766094018",
            "END" => "1766108718",
            "CHANNEL" => "/tv/pirveli_arkhi",
            "SERVER" => "https://proxy2.streamer.mediabox.ge/s01/8080",
            "TOKEN" => "caa4e64f62becd697cafb4451223fc02d1f14660-da2ca4953aacf8e6d6f49f2368f2cfde-1766108718-1766094018",
            "FILE" => "/index.m3u8"
        ];

        $name = basename($oldData['CHANNEL']); 

        Channel::create([
            'id' => null,
            'name' => $name,
            'description' => null,
            'stream_url' => $oldData['URL'],
            'thumbnail_url' => null,
            'icon_url' => null,
            'category_id' => "4236cb6d-262f-4d42-9fe7-85c98bfc7d99",
            'is_active' => true,
            'is_vip_only' => false,
            'view_count' => 0,
        ]);
    }
}
