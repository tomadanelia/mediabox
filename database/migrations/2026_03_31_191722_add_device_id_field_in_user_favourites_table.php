<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_favourites', function (Blueprint $table) {
            $table->string('device_id')->default('spa_web')->after('user_id');
            $table->dropForeign(['user_id']);
            $table->dropForeign(['channel_id']);
            $table->dropUnique(['user_id', 'channel_id']);
            $table->unique(['user_id', 'channel_id', 'device_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('user_favourites', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['channel_id']);
            
            $table->dropUnique(['user_id', 'channel_id', 'device_id']);
            
            $table->unique(['user_id', 'channel_id']);
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            
            $table->dropColumn('device_id');
        });
    }
};