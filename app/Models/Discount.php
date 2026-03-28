<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Discount extends Model
{
    protected $fillable = [
        'name', 
        'value', 
        'target_id', 
        'starts_at', 
        'expires_at', 
        'is_active', 
        'is_global'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'is_active' => 'boolean',
        'is_global' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'discount_user');
    }
}