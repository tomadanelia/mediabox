<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
        $table->id();
        $table->string('name'); 
        $table->decimal('value', 10, 2);
        $table->foreignUuid('target_id')->nullable()->constrained('subscription_plans')->onDelete('cascade');
        $table->timestamp('starts_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->boolean('is_active')->default(true);
        $table->boolean('is_global')->default(false);
        $table->timestamps();
    });

    Schema::create('discount_user', function (Blueprint $table) {
        $table->foreignId('discount_id')->constrained('discounts')->onDelete('cascade');
        $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
        $table->primary(['discount_id', 'user_id']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_user');
        Schema::dropIfExists('discounts');
    }
};
