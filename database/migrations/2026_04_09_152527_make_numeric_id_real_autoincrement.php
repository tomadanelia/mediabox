<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{


public function up(): void
{
    $driver = Schema::getConnection()->getDriverName();

    if ($driver === 'mysql') {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('numeric_id')->unique()->change();
        });
        DB::statement('ALTER TABLE users MODIFY numeric_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;');
        DB::statement('ALTER TABLE users AUTO_INCREMENT = 600000;');
    }
    }

    public function down(): void
{
    $driver = Schema::getConnection()->getDriverName();

    if ($driver === 'mysql') {
        DB::statement('ALTER TABLE users MODIFY numeric_id BIGINT UNSIGNED NOT NULL;');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['numeric_id']);
        });
    }
}
};
