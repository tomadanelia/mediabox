<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Channel;

class ChannelSeeder extends Seeder
{

    public function run(): void
    {



        Channel::firstOrCreate(
            ['external_id' => '22'], 
            [
                'number' => 1,
                'epg_id' => 'magti_3',
                'name_ka' => "პირველი არხი",
                'name_en' => "Pirveli Arkhi",
                'description_ka' => null,
                'description_en' => null,
                'icon_url' => "https://img.mediabox.ge/22.png",
                'category_id' => "4236cb6d-262f-4d42-9fe7-85c98bfc7d99", 
                'is_active' => true,
                'is_vip_only' => false,
                'view_count' => 0,
            ]
        );
    }
}
