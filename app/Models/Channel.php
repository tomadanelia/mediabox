<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Channel extends Model
{
    use HasUuid;

     protected $fillable = [
        'external_id',  
        'number',       
        'epg_id',       
        'name_ka',
        'name_en',
        'description_ka',
        'description_en',
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