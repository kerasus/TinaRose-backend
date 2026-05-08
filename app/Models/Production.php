<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    protected $fillable = [
        'type',
        'user_id',         // اپراتور
        'recorded_by',     // ثبت‌کننده رکورد
        'product_part_id',
        'fabric_id',
        'color_id',
        'product_id',
        'approved_by',
        'approved_at',
        'production_date',
        'bunch_count',
        'description',
        'downtime_reason_id',
        'downtime_duration',
        'equipment_id'
    ];

    protected $casts = [
        'production_date' => 'date',
        'approved_at' => 'datetime'
    ];

    // deprecated: use -> operator
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // اپراتور واقعی تولید
    public function operator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // کسی که رکورد را ثبت کرده
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function productPart()
    {
        return $this->belongsTo(ProductPart::class, 'product_part_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function fabric()
    {
        return $this->belongsTo(Fabric::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function downtimeReason()
    {
        return $this->belongsTo(DowntimeReason::class, 'downtime_reason_id');
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    // ==============================
    // Scopes
    // ==============================
    public function scopeUnapproved($query)
    {
        return $query->whereNull('approved_at');
    }

    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_at');
    }

    public function scopeApprovedBy($query, int $userId)
    {
        return $query->where('approved_by', $userId);
    }

    public function scopeSummaryQuery($query,
                                      $roleName = null,
                                      $dateFrom = null,
                                      $dateTo = null,
                                      $productPartId = null,
                                      $colorId = null,
                                      $userId = null,
                                      $fabricId = null,
                                      $groupByUser = false)
    {
        $query->join('product_parts', 'productions.product_part_id', '=', 'product_parts.id')
            ->leftJoin('colors', 'productions.color_id', '=', 'colors.id')
            ->leftJoin('fabrics', 'productions.fabric_id', '=', 'fabrics.id')
            ->leftJoin('downtime_reasons', 'productions.downtime_reason_id', '=', 'downtime_reasons.id')
            ->leftJoin('equipments', 'productions.equipment_id', '=', 'equipments.id');

        if ($groupByUser) {
            $query->join('users', 'productions.user_id', '=', 'users.id');
        }

        $selectFields = [
            'product_parts.name as product_part_name',
            DB::raw('colors.name as color_name'),
            DB::raw('fabrics.name as fabric_name'),
            DB::raw('SUM(productions.bunch_count) as total_bunch'),
            DB::raw('SUM(productions.bunch_count * product_parts.count_per_bunch) as total_petals'),
            // Excel extra fields (aggregated over the group)
            DB::raw('GROUP_CONCAT(DISTINCT equipments.title SEPARATOR ", ") as equipment'),
            DB::raw('GROUP_CONCAT(DISTINCT equipments.code SEPARATOR ", ") as equipment_code'),
            DB::raw('GROUP_CONCAT(DISTINCT downtime_reasons.name SEPARATOR ", ") as downtime_reason_title'),
            DB::raw('COALESCE(SUM(productions.downtime_duration), 0) as downtime_duration'),
        ];

        if ($groupByUser) {
            $selectFields = array_merge([
                'users.id as user_id',
                'users.employee_code',
                'users.firstname',
                'users.lastname',
                'users.username'
            ], $selectFields);
        }

        $query->select($selectFields);

        $groupByFields = ['product_parts.id', 'product_parts.name', 'colors.id', 'colors.name', 'fabrics.id', 'fabrics.name'];

        if ($groupByUser) {
            $groupByFields = array_merge(['users.id', 'users.employee_code', 'users.firstname', 'users.lastname', 'users.username'], $groupByFields);
        }

        $query->groupBy($groupByFields);

        if ($dateFrom && $dateTo) {
            $query->whereBetween('productions.production_date', [$dateFrom, $dateTo]);
        } elseif ($dateFrom) {
            $query->where('productions.production_date', '>=', $dateFrom);
        } elseif ($dateTo) {
            $query->where('productions.production_date', '<=', $dateTo);
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
