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
    DB::statement("ALTER TABLE user_subscriptions MODIFY started_at DATETIME NOT NULL");
    DB::statement("ALTER TABLE user_subscriptions MODIFY expires_at DATETIME NOT NULL");

    DB::statement("ALTER TABLE discounts MODIFY starts_at DATETIME NULL");
    DB::statement("ALTER TABLE discounts MODIFY expires_at DATETIME NULL");
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->timestamp('started_at')->change();
            $table->timestamp('expires_at')->change();
        });

        Schema::table('discounts', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->change();
            $table->timestamp('expires_at')->nullable()->change();
        });

    }
};