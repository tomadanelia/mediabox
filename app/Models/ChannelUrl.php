<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelUrl extends Model
{
    protected $table = 'channel_urls';
    
    protected $fillable = [
        'channel_id', 'channel_url', 'url_type', 'filter', 'priority'
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_id', 'external_id');
    }
}