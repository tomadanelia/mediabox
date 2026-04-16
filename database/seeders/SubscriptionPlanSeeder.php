<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
   public const FREE_PLAN_ID = '00000000-0000-0000-0000-000000000000';

   public function run(): void
{
    SubscriptionPlan::updateOrCreate(
        ['id' => self::FREE_PLAN_ID],
        [
            'name_en' => 'Free Package',
            'price' => 0,
            'duration_days' => 36500,
            'is_active' => true,
        ]
    );
}
}