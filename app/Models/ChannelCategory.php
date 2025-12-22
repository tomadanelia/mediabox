<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
class ChannelCategory extends Model
{
    use HasUuid;
    protected $fillable = [
        'name',
        'description',
        'icon_url',
    ];
    public function channels()
    {
        return $this->hasMany(Channel::class, 'category_id');
    }
  
}
