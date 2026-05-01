<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::table('subscription_plans', function (Blueprint $table) {
        $table->enum('platform', ['web', 'stb', 'all'])->default('all')->after('is_active');
        $table->boolean('is_default')->default(false)->after('platform');
    });
}

    public function down(): void
{
    Schema::table('subscription_plans', function (Blueprint $table) {
        $table->dropColumn(['platform', 'is_default']);
    });
}
};
