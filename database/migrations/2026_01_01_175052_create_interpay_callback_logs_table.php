<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
       Schema::create('interpay_callback_logs', function (Blueprint $table) {
            $table->id();

            $table->string('payment_id')->nullable();
            $table->string('op'); 

            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();

            $table->json('response_body')->nullable();
            $table->integer('response_status')->nullable();

            $table->string('ip_address', 45)->nullable();

            $table->timestamp('received_at')->useCurrent();
        });
    }

  
    public function down(): void
    {
        Schema::dropIfExists('interpay_callback_logs');
    }
};
