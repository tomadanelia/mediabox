<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InterpayPayment extends Model
{
    use HasFactory;

    protected $table = 'interpay_payments';
    protected $fillable = [
        'payment_id',
        'account_id',
        'service_id',
        'amount_tetri',
        'amount_lari',
        'status',
        'provider',
        'terminal',
    ];


    protected $casts = [
        'account_id' => 'string',           
        'amount_tetri' => 'integer',
        'amount_lari' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }
}