<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
       Schema::create('users', function (Blueprint $table) {
        $table->uuid('id')->primary(); 
        $table->string('username')->unique();
        $table->string('email')->unique();
        $table->string('password'); 
        $table->string('full_name')->nullable();
        $table->string('avatar_url')->nullable();
        $table->string('role')->default('user'); 
        $table->string('subscription_status')->default('free'); 
        $table->timestamp('subscription_expires_at')->nullable();
        $table->timestamps(); 
    });

    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
