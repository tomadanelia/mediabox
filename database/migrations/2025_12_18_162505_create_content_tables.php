<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::create('channel_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('icon_url')->nullable();
            $table->timestamps();
        });
        Schema::create('channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('stream_url');
            $table->string('thumbnail_url')->nullable();
            $table->string('icon_url')->nullable();
            $table->foreignId('category_id')->constrained('channel_categories')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_vip_only')->default(false);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamps();
        });
        Schema::create('channel_recordings', function (Blueprint $table) {
           $table->uuid('id')->primary();
        $table->foreignUuid('channel_id')->constrained('channels')->onDelete('cascade');
        $table->string('title')->nullable();
        $table->text('description')->nullable();
        $table->string('video_url');
        $table->string('thumbnail_url')->nullable();
        $table->integer('duration_seconds')->default(0);
        $table->boolean('is_vip_only')->default(false);
        $table->unsignedBigInteger('view_count')->default(0);
        $table->timestamp('recorded_at')->nullable();
        $table->timestamps();
        });
    }

  
    public function down(): void
    {
        Schema::dropIfExists('channels');
        Schema::dropIfExists('channel_categories');
        Schema::dropIfExists('channel_recordings');
    }
};
