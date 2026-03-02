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
    Schema::create('tv_pairings', function (Blueprint $table) {
    $table->id();
    $table->string('pairing_code', 10)->unique();
    $table->string('device_id')->index();
    $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('cascade');
    $table->timestamp('expires_at');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tv_pairings');
    }
};
