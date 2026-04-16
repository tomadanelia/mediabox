<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_urls', function (Blueprint $table) {
            $table->id(); 
            $table->string('channel_id'); 
            $table->string('channel_url', 2083);
            $table->integer('url_type')->default(0);
            $table->string('filter', 20)->nullable();
            $table->integer('priority')->default(0);
            $table->timestamps();
            $table->foreign('channel_id')
                  ->references('external_id')
                  ->on('channels')
                  ->onDelete('cascade');
        });

        Schema::create('channel_archive_urls', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id');
            $table->string('channel_url', 2083);
            $table->integer('url_type')->default(0);
            $table->string('filter', 20)->nullable();
            $table->integer('priority')->default(0);
            $table->integer('archive_length')->default(24);
            $table->timestamps();
            $table->foreign('channel_id')
                  ->references('external_id')
                  ->on('channels')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_archive_urls');
        Schema::dropIfExists('channel_urls');
    }
};