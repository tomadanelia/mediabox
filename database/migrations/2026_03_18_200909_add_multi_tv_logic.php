<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
         Schema::table('subscription_plans', function (Blueprint $table) {
        $table->decimal('extra_tv_price', 10, 2)->default(0)->after('price');
    });

    Schema::table('user_subscriptions', function (Blueprint $table) {
        $table->integer('device_limit')->default(1)->after('is_active');
    });
    }

    public function down(): void
    {

    }
};
