<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
class UserDevice extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'fcm_token'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}