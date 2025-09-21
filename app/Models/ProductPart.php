<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPart extends Model
{
    protected $fillable = ['name', 'code', 'initial_stock', 'count_per_bunch'];
}
