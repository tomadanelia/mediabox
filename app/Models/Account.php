<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Account extends Model
{
        use HasUuid;
    protected $fillable = [
        'user_id',
        'balance',
    ];
    

    public function user()
    {
    return $this->belongsTo(User::class);
    }
}
