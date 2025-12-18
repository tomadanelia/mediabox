<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;  
use Illuminate\Support\Str;        
class ChannelCategory extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('channel_categories')->insert([
            'id' => "4236cb6d-262f-4d42-9fe7-85c98bfc7d99",
            'name' => 'Standard Channels',
            'description' => 'Default category for standard channels',
            'icon_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
