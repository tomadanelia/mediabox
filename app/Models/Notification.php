<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use App\Traits\HasUuid;
class Notification extends Model
{
    use HasFactory;
    use HasUuid;

    protected $table = 'notifications';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'type',
        'user_id',
        'title',
        'payload',
        'scheduled_at',
        'sent_at',
        'status',
    ];

    protected $casts = [
        'payload' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function readReceipts()
{
    return $this->hasMany(NotificationReadReceipt::class, 'notification_id');
}
}