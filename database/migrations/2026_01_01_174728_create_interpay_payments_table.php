<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('interpay_payments', function (Blueprint $table) {
            $table->id();

            $table->string('payment_id')->unique(); 
           $table->uuid('account_id') 
        ->nullable(false)
        ->constrained('accounts') 
        ->onDelete('cascade');
            $table->string('service_id');

            $table->integer('amount_tetri');
            $table->decimal('amount_lari', 12, 2)->nullable();

            $table->string('status');

            $table->string('provider');
            $table->string('terminal');
            $table->timestamps();
        });
    }

  
    public function down(): void
    {
        Schema::dropIfExists('interpay_payments');
    }
};
