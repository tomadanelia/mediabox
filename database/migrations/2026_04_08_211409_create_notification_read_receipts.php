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
    Schema::create('notification_read_receipts', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('notification_id')->constrained('notifications')->cascadeOnDelete();
    $table->timestamp('read_at')->useCurrent();
    $table->unique(['user_id', 'notification_id']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_read_receipts');
    }
};
