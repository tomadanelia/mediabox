<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Channel extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'description',
        'stream_url',
        'thumbnail_url',
        'icon_url',
        'category_id',
        'is_active',
        'is_vip_only',
        'view_count'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_vip_only' => 'boolean',
        'view_count' => 'integer',
    ];
}