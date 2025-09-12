<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    protected $fillable = [
        'user_id', 'product_part_id', 'fabric_id', 'color_id',
        'production_date', 'bunch_count', 'description'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function productPart()
    {
        return $this->belongsTo(ProductPart::class, 'product_part_id');
    }

    public function fabric()
    {
        return $this->belongsTo(Fabric::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function scopeSummaryQuery($query, $roleName = null, $date = null, $productPartId = null, $colorId = null, $userId = null, $fabricId = null)
    {
        $query->join('product_parts', 'productions.product_part_id', '=', 'product_parts.id')
            ->leftJoin('colors', 'productions.color_id', '=', 'colors.id')
            ->leftJoin('fabrics', 'productions.fabric_id', '=', 'fabrics.id')
            ->select([
                'product_parts.name as product_part_name',
                DB::raw('colors.name as color_name'),
                DB::raw('fabrics.name as fabric_name'),
                DB::raw('SUM(productions.bunch_count) as total_bunch'),
                DB::raw('SUM(productions.bunch_count * product_parts.count_per_bunch) as total_petals')
            ])
            ->groupBy('product_parts.id', 'product_parts.name', 'colors.id', 'colors.name', 'fabrics.id', 'fabrics.name');
//            ->groupBy('product_parts.id', 'product_parts.name', 'colors.id', 'colors.name');

        if ($date) {
            $query->where('productions.production_date', $date);
        }

        if ($roleName) {
            $query->join('model_has_roles', 'productions.user_id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('roles.name', $roleName);
        }

        if ($productPartId) {
            $query->where('product_parts.id', $productPartId);
        }

        if ($colorId) {
            $query->where('productions.color_id', $colorId);
        }

        if ($userId) {
            $query->where('productions.user_id', $userId);
        }

        if ($fabricId) {
            $query->where('productions.fabric_id', $fabricId);
        }

        return $query;
    }
}
