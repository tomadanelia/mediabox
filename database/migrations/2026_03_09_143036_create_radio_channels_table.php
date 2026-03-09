<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radio_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_id')->unique()->index(); 
            $table->string('name');
            $table->string('icon_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_free')->default(false);
            $table->timestamps();
        });

        Schema::create('radio_subscription_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('radio_id')->constrained('radio_channels')->onDelete('cascade');
            $table->foreignUuid('plan_id')->constrained('subscription_plans')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_subscription_plan');
        Schema::dropIfExists('radio_channels');
    }
};