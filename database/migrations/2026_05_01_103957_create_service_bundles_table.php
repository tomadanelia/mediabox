<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // database/migrations/2026_05_01_000001_create_service_bundles_table.php

public function up(): void
{
    // The named bundle (e.g. "Main TV", "Sports Radio")
    Schema::create('service_bundles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('slug')->unique();        // 'main_tv', 'sports_radio'
        $table->string('name');
        $table->enum('type', ['tv', 'radio', 'module']);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    // plan → bundle join (replaces channel_subscription_plan logic)
    Schema::create('plan_services', function (Blueprint $table) {
        $table->id();
        $table->uuid('plan_id');
        $table->uuid('bundle_id');
        $table->unique(['plan_id', 'bundle_id']);
        $table->foreign('plan_id')->references('id')->on('subscription_plans')->onDelete('cascade');
        $table->foreign('bundle_id')->references('id')->on('service_bundles')->onDelete('cascade');
    });

    // the actual content list inside a bundle
    Schema::create('bundle_items', function (Blueprint $table) {
        $table->id();
        $table->uuid('bundle_id');
        $table->unsignedTinyInteger('item_type'); // 1=tv, 2=radio, 3=module
        $table->uuid('item_id');                  // points to channels.id, radio_channels.id, or app_modules.id
        $table->unique(['bundle_id', 'item_type', 'item_id']);
        $table->foreign('bundle_id')->references('id')->on('service_bundles')->onDelete('cascade');
        // no FK on item_id — it's polymorphic across 3 tables
    });

    // feature flags (weather, currency, etc.)
    Schema::create('app_modules', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('slug')->unique();  // 'weather', 'currency', 'radio', 'cinema'
        $table->string('name');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('bundle_items');
    Schema::dropIfExists('plan_services');
    Schema::dropIfExists('app_modules');
    Schema::dropIfExists('service_bundles');
}
};
