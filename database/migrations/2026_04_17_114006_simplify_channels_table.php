<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('name')->after('external_id')->nullable();
        });
        DB::table('channels')->update([
            'name' => DB::raw("COALESCE(name_en, name_ka)")
        ]);

        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['name_ka', 'name_en', 'description_ka', 'description_en']);
            $table->string('name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('name_ka')->nullable();
            $table->string('name_en')->nullable();
            $table->text('description_ka')->nullable();
            $table->text('description_en')->nullable();
        });
        
        DB::table('channels')->update([
            'name_ka' => DB::raw('name'),
            'name_en' => DB::raw('name')
        ]);
        
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};