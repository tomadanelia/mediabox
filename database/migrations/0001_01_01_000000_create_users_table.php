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
        $table->string('email')->nullable()->unique();
        $table->string('phone')->nullable()->unique();
        $table->string('password'); 
        $table->string('full_name')->nullable();
        $table->string('avatar_url')->nullable();
        $table->string('role')->default('user'); 
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamp('phone_verified_at')->nullable();
        $table->timestamps(); 
    });

    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
