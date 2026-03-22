<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{

    protected $fillable = [
        'name',
        'tax_id',
        'purpose'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}