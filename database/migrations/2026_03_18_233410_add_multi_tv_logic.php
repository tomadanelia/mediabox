<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
   
    public function up(): void
    {
         Schema::table('users', function (Blueprint $table) {
        $table->integer('tv_limit')->default(1)->after('role');
    });
      DB::table('site_settings')->updateOrInsert(
        ['key' => 'extra_tv_slot_price'],
        ['value' => '5.00'] 
    );
    }

    public function down(): void
    {
        
    }
};
