<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
class AppModule extends Model {
    use HasUuid;
    protected $fillable = ['slug', 'name', 'is_active'];
}
