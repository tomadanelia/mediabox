<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class BundleItem extends Model {
    public $timestamps = false;
    protected $fillable = ['bundle_id', 'item_type', 'item_id'];
    public function bundle() { return $this->belongsTo(ServiceBundle::class); }
}
