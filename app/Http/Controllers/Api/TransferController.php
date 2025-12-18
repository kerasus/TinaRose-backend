<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Color;
use App\Services\Transfer\TransferApprovalService;
use App\Services\Transfer\TransferCreationService;
use App\Services\Transfer\TransferDeletionService;
use App\Services\Transfer\TransferInventoryResolver;
use App\Services\Transfer\TransferUpdateService;
use App\Services\Transfer\TransferValidationService;
use App\Traits\Filter;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\Inventory;
use App\Traits\CommonCRUD;
use App\Models\RawMaterial;
use App\Models\ProductPart;
use Illuminate\Http\Request;
use App\Models\TransferItem;
use App\Models\InventoryItem;
use Illuminate\Validation\Rule;
use App\Enums\TransferStatusType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Services\TransferItemStrategy\StrategyResolver;

class TransferController extends Controller
{
    use Filter, CommonCRUD;

    private TransferCreationService $creationService;
    private TransferUpdateService $updateService;
    private TransferDeletionService $deletionService;
    private TransferApprovalService $approvalService;
    private TransferValidationService $validationService;
    private TransferInventoryResolver $inventoryResolver;

    public function __construct(
        TransferCreationService $creationService,
        TransferUpdateService $updateService,
        TransferDeletionService $deletionService,
        TransferApprovalService $approvalService,
        TransferValidationService $validationService,
        TransferInventoryResolver $inventoryResolver
    ) {
        $this->creationService = $creationService;
        $this->updateService = $updateService;
        $this->deletionService = $deletionService;
        $this->approvalService = $approvalService;
        $this->validationService = $validationService;
        $this->inventoryResolver = $inventoryResolver;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['description'],
            'filterKeysExact' => ['status'],
            'filterOrKeysExact' => [
                'from_user_id',
                'to_user_id',
                'creator_user_id',
            ],
            'filterDates' => ['transfer_date'],
            'filterRelationKeys'=> [
                [
                    'requestKey' => 'type',
                    'relationName' => 'fromInventory',
                    'relationColumn' => 'type'
                ],
                [
                    'requestKey' => 'type',
                    'relationName' => 'toInventory',
                    'relationColumn' => 'type'
                ]
            ],
            'eagerLoads' => [
                'fromUser',
                'toUser',
                'fromInventory',
                'toInventory',
                'creator',
                'items.item'
            ]
        ];

        return $this->commonIndex($request, Transfer::class, $config);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Throwable
     */
    public function store(Request $request): JsonResponse
    {
        $validTypes = [
            'fabric_cutter',
            'coloring_worker',
            'molding_worker',
            'assembler',
            'central_warehouse'
        ];

        $request->validate([
            'from_inventory_type' => 'nullable|string|in:' . implode(',', $validTypes),
            'to_inventory_type'   => 'nullable|string|in:' . implode(',', $validTypes),
            'from_user_id'        => 'nullable|exists:users,id',
            'to_user_id'          => 'nullable|exists:users,id',
            'transfer_date'       => 'required|date_format:Y-m-d',
            'description'         => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.item_type'   => [
                'required',
                'string',
                Rule::in([
                    ProductPart::class,
                    RawMaterial::class,
                    Product::class
                ])
            ],
            'items.*.item_id'     => 'required|integer',
            'items.*.color_id'    => 'nullable|exists:colors,id',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.notes'       => 'nullable|string'
        ]);

        try {
            $this->validationService->validateItems($request->items);
            $transfer = $this->creationService->create($request->all());

            return $this->show($transfer->id);
        } catch (\Exception $e) {
            // اگر خطا داخل تراکنش بوده باشه
            return response()->json([
                'message' => 'خطا در ایجاد حواله',
                'errors' => [
                    $e->getMessage()
                ]
            ], 422);
        }
    }

    /**
     * Update inventory based on transfer items.
     * @throws ValidationException
     */
    private function updateInventoryOnApproved(Transfer $transfer): void
    {
        foreach ($transfer->items as $item) {
            $strategy = StrategyResolver::resolve($item);

            // From inventory
            if ($transfer->from_inventory_id) {
                $fromItem = InventoryItem::firstOrNew(
                    [
                        'inventory_id' => $transfer->from_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type,
                        'color_id' => $item->color_id,
                    ], [
                        'quantity' => 0
                    ]
                );

                if (!$fromItem->exists) {
                    $fromItem->save();
                }

                $strategy->handleOutgoing($fromItem, $item->quantity);
            }

            // To inventory
            if ($transfer->to_inventory_id) {
                $toItem = InventoryItem::firstOrNew(
                    [
                        'inventory_id' => $transfer->to_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type,
                        'color_id' => $item->color_id,
                    ], [
                        'quantity' => 0
                    ]
                );

                if (!$toItem->exists) {
                    $toItem->save();
                }

                $strategy->handleIncoming($toItem, $item->quantity);
            }
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $transfer = Transfer::with([
            'fromUser:id,firstname,lastname,username',
            'toUser:id,firstname,lastname,username',
            'fromInventory:id,user_id,name',
            'toInventory:id,user_id,name',
            'items.item',
            'items.color',
        ])->findOrFail($id);

        return $this->jsonResponseOk($transfer);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Transfer $transfer
     * @return JsonResponse
     */
    public function update(Request $request, Transfer $transfer): JsonResponse
    {
        $request->validate([
            'transfer_date' => 'sometimes|required|date_format:Y-m-d',
            'description' => 'sometimes|nullable|string',
            'items' => 'sometimes|required|array|min:1',
            'items.*.item_type' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    ProductPart::class,
                    RawMaterial::class,
                    Product::class
                ])
            ],
            'items.*.item_id' => 'sometimes|required|integer',
            'items.*.color_id' => 'sometimes|nullable|exists:colors,id',
            'items.*.quantity' => 'sometimes|required|numeric|min:0.01',
            'items.*.notes' => 'sometimes|nullable|string'
        ]);

        try {
            $updatedTransfer = $this->updateService->update($transfer, $request->all());
            return $this->show($updatedTransfer->id);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در ویرایش حواله',
                'errors' => [
                    $e->getMessage()
                ]
            ], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Transfer $transfer
     * @return JsonResponse
     * @throws \Throwable
     */
    public function destroy(Transfer $transfer): JsonResponse
    {
        try {
            $this->deletionService->delete($transfer);

            return response()->json(null, 204); // No Content
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'خطا در حذف حواله',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در حذف حواله',
                'errors' => [
                    $e->getMessage()
                ]
            ], 422);
        }
    }

    /**
     * Approve a pending transfer.
     *
     * @param Request $request
     * @param Transfer $transfer
     * @return JsonResponse
     */
    public function approve(Request $request, Transfer $transfer): JsonResponse
    {
        if ($transfer->status !== TransferStatusType::Pending) {
            return response()->json([
                'message' => 'این حواله قبلاً تأیید یا رد شده است.'
            ], 422);
        }

        // فقط گیرنده می‌تونه تأیید کنه
        if ($transfer->to_user_id !== auth()->id()) {
            return response()->json([
                'message' => 'فقط گیرنده این حواله می تواند تأیید کنه.'
            ], 403);
        }

        $transfer->update([
            'status' => TransferStatusType::Approved,
            'approved_at' => now()
        ]);

        // آپدیت انبارها
        $this->updateInventoryOnApproved($transfer);

        return response()->json([
            'message' => 'حواله با موفقیت تأیید شد.',
            'transfer' => $transfer
        ]);
    }

    public function reject(Request $request, Transfer $transfer): JsonResponse
    {
        if ($transfer->status !== TransferStatusType::Pending) {
            return response()->json([
                'message' => 'این حواله قبلاً تأیید یا رد شده است.'
            ], 422);
        }

        if ($transfer->to_user_id !== auth()->id()) {
            return response()->json([
                'message' => 'فقط گیرنده این حواله می تواند رد کنه.'
            ], 403);
        }

        $transfer->update([
            'status' => TransferStatusType::Rejected,
            'rejected_at' => now()
        ]);

        return response()->json([
            'message' => 'حواله با موفقیت رد شد.',
            'transfer' => $transfer
        ]);
    }

    /**
     * Add a single item to an existing transfer.
     *
     * @param Request $request
     * @param Transfer $transfer
     * @return JsonResponse
     */
    public function addItem(Request $request, Transfer $transfer): JsonResponse
    {
        $request->validate([
            'item_type' => 'required|string|in:App\Models\ProductPart,App\Models\RawMaterial,App\Models\Product',
            'item_id' => 'required|integer|exists:\App\Models\{item_type},id',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string'
        ]);

        $transfer->items()->create($request->all());

        return $this->show($transfer->id);
    }

    /**
     * Remove a specific item from the transfer.
     *
     * @param Transfer $transfer
     * @param TransferItem $item
     * @return JsonResponse
     */
    public function removeItem(Transfer $transfer, TransferItem $item): JsonResponse
    {
        if ($item->transfer_id !== $transfer->id) {
            return response()->json([
                'error' => 'این آیتم متعلق به این حواله نیست.'
            ], 403);
        }

        $item->delete();

        return response()->json(null, 204); // No Content
    }
}
