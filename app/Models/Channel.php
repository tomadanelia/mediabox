<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use App\Models\ChannelCategory;

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
        'access_level',
        'view_count'
    ];


    protected $casts = [
        'is_active' => 'boolean',
        'view_count' => 'integer',
    ];
    public function category()
    {
        return $this->belongsTo(ChannelCategory::class, 'category_id');
    }
}