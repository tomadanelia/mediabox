<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    public const FREE_PLAN_ID = '00000000-0000-0000-0000-000000000000';
    public const STANDARD_PLAN_ID = '11111111-1111-1111-1111-111111111111';

    public function run(): void
    {
        SubscriptionPlan::updateOrCreate(
            ['id' => self::FREE_PLAN_ID],
            [
                'name_ka' => 'უფასო პაკეტი',        
                'name_en' => 'Free Package',       
                'description_ka' => 'უფასო არხები', 
                'description_en' => 'Free Channels', 
                'price' => 0.00,
                'duration_days' => 36500,
                'is_active' => true,
            ]
        );
        SubscriptionPlan::updateOrCreate(
            ['id' => self::STANDARD_PLAN_ID], 
            [
                'name_en' => 'Standard Package',
                'name_ka' => 'სტანდარტული პაკეტი',        
                'description_ka' => 'სტანდარტული არხები', 
                'description_en' => 'Standard Channels', 
                'price' => 9.99,
                'duration_days' => 30,
                'is_active' => true,
            ]
        );
    }
}