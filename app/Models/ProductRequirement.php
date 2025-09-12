<?php

namespace App\Models;

use App\Enums\RequiredItemType;
use Illuminate\Database\Eloquent\Model;

class ProductRequirement extends Model
{
    protected $fillable = [
        'product_id',
        'required_item_id',
        'required_item_type',
        'quantity',
        'unit'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'decimal:2',
        'required_item_id' => 'integer',
        'required_item_type' => RequiredItemType::class,
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function requiredItem()
    {
        return $this->morphTo('required_item', 'required_item_type', 'required_item_id');
    }
}
