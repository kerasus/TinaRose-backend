<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Color;
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

        // --- ولیدیشن آیتم‌ها ---
        foreach ($request->items as $index => $item) {
            $itemType = $item['item_type'] ?? null;
            $itemId = $item['item_id'] ?? null;
            $colorId = $item['color_id'] ?? null;
            $rowCounter = $index + 1;

            if (!class_exists($itemType)) {
                return response()->json([
                    'errors' => [
                        'validate_outgoing' => "نوع آیتم نامعتبر در ردیف {$rowCounter}: {$itemType}"
                    ]
                ], 422);
            }

            if (!is_subclass_of($itemType, \Illuminate\Database\Eloquent\Model::class)) {
                return response()->json([
                    'errors' => [
                        'validate_outgoing' => "مدل ارث‌برده از Eloquent نیست در ردیف {$rowCounter}: {$itemType}"
                    ]
                ], 422);
            }

            $exists = $itemType::where('id', $itemId)->exists();
            if (!$exists) {
                return response()->json([
                    'errors' => [
                        'validate_outgoing' => "آیتم با شناسه {$itemId} در مدل {$itemType} یافت نشد (ردیف {$rowCounter})."
                    ]
                ], 422);
            }

            if ($itemType === Product::class && !$colorId) {
                return response()->json([
                    'errors' => [
                        'validate_outgoing' => "برای محصول در ردیف {$rowCounter}، رنگ الزامی است."
                    ]
                ], 422);
            }

            if (
                ($request->from_inventory_type || $request->from_user_id) &&
                $itemType === ProductPart::class &&
                !$colorId
            ) {
                return response()->json([
                    'errors' => [
                        'validate_outgoing' => "برای زیر محصول در ردیف {$rowCounter}، رنگ الزامی است."
                    ]
                ], 422);
            }

            if ($colorId && !Color::find($colorId)) {
                return response()->json([
                    'errors' => [
                        'validate_outgoing' => "رنگ با شناسه {$colorId} یافت نشد (ردیف {$rowCounter})."
                    ]
                ], 422);
            }
        }

        try {
            // --- اجرای کامل در یک تراکنش ---
            $transfer = DB::transaction(function () use ($request, $validTypes) {
                // --- تعیین انبار مبدأ ---
                $fromInventory = null;
                if ($request->from_inventory_type || $request->from_user_id) {
                    $fromInventory = DB::transaction(function () use ($request, $validTypes) {
                        if ($request->from_inventory_type && in_array($request->from_inventory_type, $validTypes)) {
                            if ($request->from_inventory_type !== 'assembler') {
                                return Inventory::firstOrCreate(
                                    ['type' => $request->from_inventory_type],
                                    [
                                        'name' => $this->getInventoryNameByType($request->from_inventory_type),
                                        'description' => "انبار عمومی - {$request->from_inventory_type}",
                                        'user_id' => null,
                                        'type' => $request->from_inventory_type
                                    ]
                                );
                            }

                            if (!$request->from_user_id) {
                                throw ValidationException::withMessages([
                                    'from_user_id' => ['برای انبار نوع مونتاژکار باید کاربر مبدأ مشخص شود.']
                                ]);
                            }

                            $user = User::findOrFail($request->from_user_id);
                            return $this->getOrCreateUserInventory($user);
                        }

                        if ($request->from_user_id) {
                            return $this->getOrCreateUserInventory(User::findOrFail($request->from_user_id));
                        }

                        throw ValidationException::withMessages([
                            'from_inventory_type' => ['لطفاً حداقل یکی از «نوع انبار مبدأ» یا «کاربر مبدأ» را مشخص کنید.']
                        ]);
                    });
                }

                // --- تعیین انبار مقصد ---
                $toInventory = null;
                // اگر to_inventory_type وجود داشت، to_user_id الزامیه
                if ($request->to_inventory_type && !$request->to_user_id) {
                    throw ValidationException::withMessages([
                        'to_user_id' => ['برای انبار مقصد، کاربر گیرنده الزامی است.']
                    ]);
                }
                if ($request->to_inventory_type || $request->to_user_id) {
                    $toInventory = DB::transaction(function () use ($request, $validTypes) {
                        if ($request->to_inventory_type && in_array($request->to_inventory_type, $validTypes)) {
                            if ($request->to_inventory_type !== 'assembler') {
                                return Inventory::firstOrCreate(
                                    ['type' => $request->to_inventory_type],
                                    [
                                        'name' => $this->getInventoryNameByType($request->to_inventory_type),
                                        'description' => "انبار عمومی - {$request->to_inventory_type}",
                                        'user_id' => null,
                                        'type' => $request->to_inventory_type
                                    ]
                                );
                            }

                            if (!$request->to_user_id) {
                                throw ValidationException::withMessages([
                                    'to_user_id' => ['برای انبار نوع "assembler" باید کاربر مقصد مشخص شود.']
                                ]);
                            }

                            $user = User::findOrFail($request->to_user_id);
                            return $this->getOrCreateUserInventory($user);
                        }

                        if ($request->to_user_id) {
                            return $this->getOrCreateUserInventory(User::findOrFail($request->to_user_id));
                        }

                        throw ValidationException::withMessages([
                            'to_inventory_type' => [
                                'لطفاً حداقل یکی از «نوع انبار مقصد» یا «کاربر مقصد» را مشخص کنید.'
                            ]
                        ]);
                    });
                }

                // --- ولیدیشن موجودی فقط اگر مبدأ انباری باشه ---
                if ($fromInventory) {

                    if ($fromInventory->is_locked) {
                        throw ValidationException::withMessages([
                            'from_inventory_type' => [
                                'انبار مبدآ در حال انبارگردانی است. تا پایان انبارگردانی نمی‌توان حواله ثبت کرد.'
                            ]
                        ]);
                    }

                    // گروه‌بندی آیتم‌ها بر اساس item_id, item_type, color_id
                    $groupedItems = collect($request->items)->groupBy(function ($item) {
                        return $item['item_id'] . '-' . $item['item_type'] . '-' . ($item['color_id'] ?? 'null');
                    });

                    foreach ($groupedItems as $group) {
                        $totalQuantity = $group->sum('quantity');
                        $firstItem = $group->first();

                        $strategy = StrategyResolver::resolve($firstItem);

                        $inventoryItemData = $firstItem;
                        $inventoryItemData['quantity'] = $totalQuantity;

                        $validationError = $strategy->validateOutgoing($fromInventory->id, $firstItem);

                        if (!empty($validationError)) {
                            throw ValidationException::withMessages($validationError);
                        }
                    }
                }

                // --- ولیدیشن موجودی فقط اگر مبدأ انباری باشه ---
                if ($toInventory) {
                    if ($toInventory->is_locked) {
                        throw ValidationException::withMessages([
                            'from_inventory_type' => [
                                'انبار مقصد در حال انبارگردانی است. تا پایان انبارگردانی نمی‌توان حواله ثبت کرد.'
                            ]
                        ]);
                    }
                }

                // --- ایجاد حواله ---
                $transfer = Transfer::create([
                    'from_user_id' => $request->from_user_id,
                    'to_user_id' => $request->to_user_id,
                    'from_inventory_id' => $fromInventory ? $fromInventory->id : null,
                    'to_inventory_id' => $toInventory ? $toInventory->id : null,
                    'transfer_date' => $request->transfer_date,
                    'status' => TransferStatusType::Pending,
                    'description' => $request->description,
                ]);

                foreach ($request->items as $item) {
                    $transfer->items()->create($item);
                }

                return $transfer;
            });

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
     * Get Persian display name for inventory type.
     *
     * @param string $type
     * @return string
     */
    private function getInventoryNameByType(string $type): string
    {
        $names = [
            'fabric_cutter' => 'انبار برشکاری',
            'coloring_worker' => 'انبار رنگکاری',
            'molding_worker' => 'انبار اتوکاری',
            'assembler' => 'انبار مونتاژ کاری',
            'central_warehouse' => 'انبار مرکزی'
        ];

        return $names[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Get or create personal inventory for user
     *
     * @param User $user
     * @return Inventory
     */
    private function getOrCreateUserInventory($user): Inventory
    {
        return Inventory::firstOrCreate(
            [
                'name' => $user->firstname . ' ' . $user->lastname,
                'description' => "انبار شخصی {$user->firstname}",
                'user_id' => $user->id,
                'type' => 'assembler'
            ]
        );
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
            'from_user_id' => 'nullable|exists:users,id',
            'to_user_id' => 'nullable|exists:users,id',
            'from_inventory_id' => 'nullable|exists:user_inventories,id',
            'to_inventory_id' => 'nullable|exists:user_inventories,id',
            'transfer_date' => 'sometimes|required|date_format:Y-m-d',
            'description' => 'sometimes|nullable|string'
        ]);

        $transfer->update($request->all());

        return $this->show($transfer->id);
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
        if ($transfer->status === TransferStatusType::Approved) {
            $validationError = $this->validateReverseApprovedTransfer($transfer);

            if (!empty($validationError)) {
                return response()->json($validationError, 422);
            }
        }

        return DB::transaction(function () use ($transfer) {
            $this->updateInventoryOnDelete($transfer);

            $transfer->items()->delete();
            $transfer->delete();

            return response()->json(null, 204);
        });
    }

    private function validateReverseApprovedTransfer (Transfer $transfer): array|bool {

        $transferItems = $transfer->items;
        $fromInventory = null;
        $toInventory = null;

        // --- بررسی انبار مبدأ ---
        if ($transfer->from_inventory_id) {
            $fromInventory = Inventory::find($transfer->from_inventory_id);

            if ($fromInventory && $fromInventory->is_locked) {
                return [
                    'errors' => [
                        'from_inventory_is_locked' => 'انبار مبدأ در حال انبارگردانی است. تا پایان انبارگردانی نمی‌توان حواله مربوط به آن را حذف کرد.'
                    ]
                ];
            }
        }

        // --- بررسی انبار مقصد ---
        if ($transfer->to_inventory_id) {
            $toInventory = Inventory::find($transfer->to_inventory_id);

            if ($toInventory && $toInventory->is_locked) {
                return [
                    'errors' => [
                        'to_inventory_is_locked' => 'انبار مقصد در حال انبارگردانی است. تا پایان انبارگردانی نمی‌توان حواله مربوط به آن را حذف کرد.'
                    ]
                ];
            }
        }

        foreach ($transferItems as $transferItem) {
            if ($fromInventory) {
                $strategy = StrategyResolver::resolve($transferItem);
                $validationError = $strategy->validateReverseOutgoing($transferItem);
                if (!empty($validationError)) {
                    return $validationError;
                }
            }

            if ($toInventory) {
                $strategy = StrategyResolver::resolve($transferItem);
                $validationError = $strategy->validateReverseIncoming($transferItem);
                if (!empty($validationError)) {
                    return $validationError;
                }
            }
        }

        return [];
    }

    /**
     * Reverse inventory changes when transfer is deleted.
     */
    private function updateInventoryOnDelete(Transfer $transfer): void
    {
        if ($transfer->status !== TransferStatusType::Approved) {
            return;
        }

        foreach ($transfer->items as $item) {
            $strategy = StrategyResolver::resolve($item);

            // Reverse: from_inventory gets back what it gave
            if ($transfer->from_inventory_id) {
                $fromItem = InventoryItem::firstOrCreate(
                    [
                        'inventory_id' => $transfer->from_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type,
                        'color_id' => $item->color_id ?? null
                    ]
                );

                $strategy->reverseOutgoing($fromItem, $item->quantity);
            }

            // Reverse: to_inventory loses what it received
            if ($transfer->to_inventory_id) {
                $toItem = InventoryItem::firstOrCreate(
                    [
                        'inventory_id' => $transfer->to_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type,
                        'color_id' => $item->color_id ?? null
                    ]
                );

                $strategy->reverseIncoming($toItem, $item->quantity);
            }
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
