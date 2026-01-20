<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
 
   public function up(): void
{
    Schema::create('subscription_plans', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name_ka');
        $table->string('name_en');
        $table->text('description_ka')->nullable();
        $table->text('description_en')->nullable();
        $table->decimal('price', 10, 2); 
        $table->integer('duration_days');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::create('payment_transactions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
        $table->foreignUuid('plan_id')->constrained('subscription_plans');
        $table->decimal('amount', 10, 2);
        $table->string('currency')->default('Gel');
        $table->string('status')->default('pending'); 
        $table->string('payment_method')->nullable(); 
        $table->json('metadata')->nullable(); 
        
        $table->timestamps();
    });

    Schema::create('user_subscriptions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
        $table->foreignUuid('plan_id')->constrained('subscription_plans');
        $table->foreignUuid('transaction_id')->nullable()->constrained('payment_transactions');
        $table->timestamp('started_at');
        $table->timestamp('expires_at');
        $table->boolean('is_active')->default(true);
        $table->boolean('auto_renew')->default(false);
        
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('user_subscriptions');
    Schema::dropIfExists('payment_transactions');
    Schema::dropIfExists('subscription_plans');
}

};
