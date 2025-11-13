<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferPackage extends Model
{
    protected $fillable = [
        'name',
        'description'
    ];

    public function items()
    {
        return $this->hasMany(TransferPackageItem::class);
    }
}
