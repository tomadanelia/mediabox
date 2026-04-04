<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RadioChannel;

class RadioChannelSeeder extends Seeder
{
    public function run(): void
    {
        $radios = [
            [
                'external_id' => 1,
                'name' => 'Radio Batumi FM 93.7',
                'icon_url' => 'https://radiomap.eu/ge/images/batumi937.gif',
                'stream_url' => 'https://nue01-edge01.itdc.ge/fm-batumi-93.7/index.m3u8',
                'is_free' => true,
            ],
            [
                'external_id' => 2,
                'name' => 'საქართველოს რადიო',
                'icon_url' => 'https://dash.cloud9.ge/streaming/radio1-cover-20230206.jpg?v=1578937886',
                'stream_url' => 'https://tv.cdn.xsg.ge/gpb-radio1/tracks-a1/mono.ts.m3u8',
                'is_free' => true,
            ],
            [
                'external_id' => 3,
                'name' => 'საქართველოს რადიო მუსიკა',
                'icon_url' => 'https://dash.cloud9.ge/streaming/radio2-cover-20230206.jpg?v=1578937886',
                'stream_url' => 'https://tv.cdn.xsg.ge/gpb-radio2/tracks-a1/mono.ts.m3u8',
                'is_free' => true,
            ],
            [
                'external_id' => 4,
                'name' => 'Radio Chveneburi',
                'icon_url' => 'https://radiomap.eu/ge/images/chveneburi.gif',
                'stream_url' => 'https://radio.cdn.xsg.ge/cld9-1050/chveneburi/index.m3u8',
                'is_free' => true,
            ],
            [
                'external_id' => 5,
                'name' => 'Radio Maestro FM 94.7',
                'icon_url' => 'https://radiomap.eu/ge/images/maestro.gif',
                'stream_url' => 'https://nue01-edge01.itdc.ge/fm-maestro-94.7/mono.m3u8',
                'is_free' => true,
            ]
        ];
    }
}