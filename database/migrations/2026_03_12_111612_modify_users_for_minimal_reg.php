<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * this has to be changed in production!!!
     */
    public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('username')->nullable()->change();
        $table->string('full_name')->nullable()->change();
        $table->integer('numeric_id')->nullable()->unique()->after('id');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('numeric_id');
        $table->string('username')->nullable(false)->change();
        $table->string('full_name')->nullable(false)->change();
    });
}
};
