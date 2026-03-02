<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use App\Models\User;
class TvPairing extends Model
{
    use Prunable;
    protected $fillable = [
        'pairing_code',
        'device_id',
        'user_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * The user who scanned the QR code.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to only get active codes.
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now())->whereNull('user_id');
    }
    public function prunable()
{
    return static::where('created_at', '<=', now()->subHour());
}
}