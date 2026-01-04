<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class InterpayCallbackLog extends Model
{
    use HasFactory;

    protected $table = 'interpay_callback_logs';


    public $timestamps = false;


    protected $fillable = [
        'payment_id',
        'op',
        'request_headers',
        'request_body',
        'response_body',
        'response_status',
        'ip_address',
        'received_at',
    ];

 
    protected $casts = [
        'request_headers' => 'array',
        'request_body' => 'array',
        'response_body' => 'array',
        'response_status' => 'integer',
        'received_at' => 'datetime',
    ];

     public function scopeRecent($query, int $days = 7)
    {
        return $query->where('received_at', '>=', now()->subDays($days));
    }

    public function scopeFailed($query)
    {
        return $query->where('response_status', '>=', 400);
    }
       public function scopeByOperation($query, string $op)
    {
        return $query->where('op', $op);
    }
}