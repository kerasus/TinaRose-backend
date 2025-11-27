<?php

namespace App\Http\Controllers\Api;

use App\Traits\Filter;
use App\Models\Production;
use App\Traits\CommonCRUD;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProductionController extends Controller
{
    use Filter, CommonCRUD;

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $config = [
            'returnModelQuery' => true,
            'filterKeys' => ['production_date'],
            'filterKeysExact'=> [
                'user_id',
                'color_id',
                'fabric_id',
                'product_id',
                'production_date',
                'product_part_id'
            ],
            'eagerLoads' => [
                'user',
                'color',
                'fabric',
                'product',
                'productPart',
            ]
        ];

        $data = $this->commonIndex($request, Production::class, $config);
        $modelQuery = $data['modelQuery'];
        $responseWithAttachedCollection = $data['responseWithAttachedCollection'];

        $requestedRole = $request->input('role');

        if ($requestedRole) {
            $role = Role::where('name', $requestedRole)->first();

            if (!$role) {
                return response()->json([
                    'نقش مورد نظر یافت نشد.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $modelQuery->whereHas('user', function ($query) use ($role) {
                $query->whereHas('roles', function ($roleQuery) use ($role) {
                    $roleQuery->where('role_id', $role->id);
                });
            });
        }

        $modelQuery->orderBy('production_date', 'desc')
            ->orderBy('id', 'desc');

        return $responseWithAttachedCollection($modelQuery);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {

        $user = $request->user();
        $roleNames = $user->getRoleNames();

        $workerRoles = ['FabricCutter', 'ColoringWorker', 'MoldingWorker'];

        $isWorker = $roleNames->intersect($workerRoles)->isNotEmpty();
        $isAssembler = $roleNames->contains('Assembler');

        $baseRules = [
            'user_id' => 'required|exists:users,id',
            'bunch_count' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'production_date' => [
                'required',
                'date_format:Y-m-d',
                ($isWorker || $isAssembler) ? 'before_or_equal:' . Carbon::now()->format('Y-m-d') : ''
            ]
        ];

        if ($isAssembler) {
            $baseRules['product_id'] = 'required|exists:products,id';
        } else {
            $baseRules['product_part_id'] = 'required|exists:product_parts,id';
        }

        $validated = Validator::make($request->all(), $baseRules)->validate();

//        $validated = Validator::make($request->all(), [
//            'user_id' => 'required|exists:users,id',
//            'product_part_id' => 'required|exists:product_parts,id',
//            'production_date' => [
//                'required',
//                'date_format:Y-m-d',
//                $isWorker ? 'before_or_equal:' . Carbon::now()->format('Y-m-d') : ''
//            ],
//            'bunch_count' => 'required|numeric|min:0.01',
//            'description' => 'nullable|string'
//        ])->validate();

        if ($roleNames->contains('FabricCutter')) {
            $request->validate([
                'fabric_id' => 'required|exists:fabrics,id'
            ]);
            $validated['fabric_id'] = $request->input('fabric_id');
        }

        if ($roleNames->contains('ColoringWorker')) {
            $request->validate([
                'color_id' => 'required|exists:colors,id'
            ]);
            $validated['color_id'] = $request->input('color_id');
        }

        if ($roleNames->contains('MoldingWorker')) {
            $request->validate([
                'color_id' => 'required|exists:colors,id'
            ]);
            $validated['color_id'] = $request->input('color_id');
        }

        if ($isAssembler) {
            $disallowedFields = ['product_part_id', 'fabric_id', 'color_id'];
            foreach ($disallowedFields as $field) {
                if ($request->has($field)) {
                    return response()->json([
                        'error' => "ثبت فیلد {$field} برای مونتاژ کار مجاز نیست."
                    ], 422);
                }
            }
        }

        $production = Production::create($validated);

        return $this->show($production->id);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $production = Production::with(['user', 'productPart', 'fabric', 'color'])->findOrFail($id);

        return $this->jsonResponseOk($production);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Production $production
     * @return JsonResponse
     */
    public function update(Request $request, Production $production): JsonResponse
    {
        $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'product_part_id' => 'sometimes|required|exists:product_parts,id',
            'fabric_id' => 'sometimes|nullable|exists:fabrics,id',
            'color_id' => 'sometimes|nullable|exists:colors,id',
            'production_date' => 'sometimes|required|date_format:Y-m-d',
            'bunch_count' => 'sometimes|required|numeric|min:0.01',
            'description' => 'sometimes|nullable|string'
        ]);

        return $this->commonUpdate($request, $production);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Production $production
     * @return JsonResponse
     */
    public function destroy(Production $production): JsonResponse
    {
        return $this->commonDestroy($production);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->validateSummaryRequest($request);

        $results = $this->getSummaryData($request, groupByUser: false, paginate: true);

        return response()->json($results);
    }

    public function userSummary(Request $request): JsonResponse
    {
        $this->validateSummaryRequest($request);

        $results = $this->getSummaryData($request, groupByUser: true, paginate: true);

        return response()->json($results);
    }

    public function summaryExport(Request $request)
    {

        $this->validateSummaryRequest($request);

        $data = $this->getSummaryData($request, groupByUser: false, paginate: false);

        if ($data->isEmpty()) {
            return response()->json([
                'داده‌ای یافت نشد.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="گزارش-تولید-' . now()->format('Y-m-d') . '.csv"',
            'Content-Encoding: UTF-8'
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            // تنظیم UTF-8 برای CSV
            fprintf($file, "\xEF\xBB\xBF");

            // هدر جدول
            fputcsv($file, ['زیر محصول', 'جمع دسته', 'جمع کل گلبرگ']);

            foreach ($data as $row) {
                fputcsv($file, [
                    $row->product_part_name,
                    $row->total_bunch,
                    $row->total_petals,
                ]);
            }

            fclose($file);
        };

        return response()->streamDownload($callback, "گزارش-تولید-" . now()->format('Y-m-d') . ".csv", $headers);
    }

    public function userSummaryExport(Request $request)
    {
        $this->validateSummaryRequest($request);

        $data = $this->getSummaryData($request, groupByUser: true, paginate: false);

        if ($data->isEmpty()) {
            return response()->json([
                'message' => 'داده‌ای یافت نشد.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="گزارش-تولید-کاربری-' . now()->format('Y-m-d') . '.csv"',
            'Content-Encoding' => 'UTF-8'
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fprintf($file, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($file, [
                'کاربر',
                'زیر محصول',
                'رنگ',
                'جمع دسته',
                'جمع کل'
            ], ',', '"');

            foreach ($data as $row) {
                fputcsv($file, [
                    ($row->firstname ?? '') . ' ' . ($row->lastname ?? '') . '(' . $row->employee_code ?? '-' . ')',
                    $row->product_part_name,
                    $row->color_name ?? 'نامشخص',
                    $row->total_bunch,
                    $row->total_petals
                ], ',', '"');
            }

            fclose($file);
        };

        return response()->streamDownload($callback, "گزارش-تولید-کاربری-" . now()->format('Y-m-d') . ".csv", $headers);
    }

    private function validateSummaryRequest(Request $request): void
    {
        $request->validate([
            'role' => 'nullable|string|exists:roles,name',
            'user_id' => 'nullable|exists:users,id',
            'color_id' => 'nullable|exists:colors,id',
            'fabric_id' => 'nullable|exists:fabrics,id',
            'product_part_id' => 'nullable|exists:product_parts,id',
            'production_date_from' => 'nullable|date_format:Y-m-d',
            'production_date_to' => 'nullable|date_format:Y-m-d',
        ], [
            'role.exists' => 'نقش مورد نظر یافت نشد.',
            'user_id.exists' => 'کاربر مورد نظر یافت نشد.',
            'color_id.exists' => 'رنگ مورد نظر یافت نشد.',
            'fabric_id.exists' => 'پارچه مورد نظر یافت نشد.',
            'product_part_id.exists' => 'زیرمحصول مورد نظر یافت نشد.',
            'production_date_from.date_format' => 'فرمت تاریخ شروع نامعتبر است.',
            'production_date_to.date_format' => 'فرمت تاریخ پایان نامعتبر است.',
        ]);

        $dateFrom = $request->input('production_date_from');
        $dateTo = $request->input('production_date_to');

        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            throw ValidationException::withMessages([
                'production_date_from' => ['تاریخ شروع باید کوچکتر یا مساوی تاریخ پایان باشد.'],
                'production_date_to' => ['تاریخ پایان باید بزرگتر یا مساوی تاریخ شروع باشد.']
            ]);
        }
    }

    private function getSummaryData(Request $request, bool $groupByUser = false, bool $paginate = false)
    {
        $query = Production::query()->summaryQuery(
            roleName: $request->input('role'),
            dateFrom: $request->input('production_date_from'),
            dateTo: $request->input('production_date_to'),
            productPartId: $request->input('product_part_id'),
            colorId: $request->input('color_id'),
            userId: $request->input('user_id'),
            fabricId: $request->input('fabric_id'),
            groupByUser: $groupByUser
        );

        if ($paginate) {
            $perPage = $request->input('per_page', 15);
            return $query->paginate($perPage);
        }

        return $query->get();
    }
}
