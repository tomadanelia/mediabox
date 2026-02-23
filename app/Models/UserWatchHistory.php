<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Channel;
class UserWatchHistory extends Model
{
    protected $table= "user_watch_history";
    protected $fillable = [
        'user_id',
        'channel_id',
        'watched_at'
    ];
    protected $casts = [
        'watched_at' => 'datetime',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function channel()
    {   return $this->belongsTo(Channel::class);
    }
}
