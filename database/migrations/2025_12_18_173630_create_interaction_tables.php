<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('user_favourites', function (Blueprint $table) {
        $table->id(); 
        $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
        $table->foreignUuid('channel_id')->constrained('channels')->onDelete('cascade');
        $table->timestamps();
            $table->unique(['user_id', 'channel_id']);
    });

    Schema::create('user_watch_history', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
        $table->foreignUuid('channel_id')->constrained('channels')->onDelete('cascade');
        $table->timestamp('watched_at');
        $table->index(['user_id', 'watched_at']);

    });
}

    
    public function down(): void
    {
        Schema::dropIfExists('interaction_tables');
    }
};
