<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_subscription_plan', function (Blueprint $table) {
            $table->id();
            
            $table->foreignUuid('channel_id')
                  ->constrained('channels')
                  ->onDelete('cascade');
                  
            $table->foreignUuid('plan_id')
                  ->constrained('subscription_plans')
                  ->onDelete('cascade');

            $table->unique(['channel_id', 'plan_id']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_subscription_plan');
    }
};