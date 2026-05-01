<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // database/migrations/2026_05_01_000004_drop_old_plan_join_tables.php

public function up(): void
{
    Schema::dropIfExists('channel_subscription_plan');
    Schema::dropIfExists('radio_subscription_plan');
}

public function down(): void
{
    // recreate them if you need to roll back
    Schema::create('channel_subscription_plan', function (Blueprint $table) {
        $table->id();
        $table->uuid('channel_id');
        $table->uuid('plan_id');
        $table->unique(['channel_id', 'plan_id']);
        $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
        $table->foreign('plan_id')->references('id')->on('subscription_plans')->onDelete('cascade');
    });
    Schema::create('radio_subscription_plan', function (Blueprint $table) {
        $table->id();
        $table->uuid('radio_id');
        $table->uuid('plan_id');
        $table->foreign('radio_id')->references('id')->on('radio_channels')->onDelete('cascade');
        $table->foreign('plan_id')->references('id')->on('subscription_plans')->onDelete('cascade');
    });
}
};
