<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelArchiveUrl extends Model
{
    protected $table = 'channel_archive_urls';

    protected $fillable = [
        'channel_id', 'channel_url', 'url_type', 'filter', 'priority', 'archive_length'
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_id', 'external_id');
    }
}