<?php

namespace App\Http\Controllers\Api;

use App\Models\InventoryItem;
use App\Models\User;
use App\Services\TransferItemStrategy\StrategyResolver;
use App\Traits\Filter;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\Inventory;
use App\Traits\CommonCRUD;
use App\Models\RawMaterial;
use App\Models\ProductPart;
use Illuminate\Http\Request;
use App\Models\TransferItem;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Observers\TransferObserver;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

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
            'filterOrKeysExact' => [
                'from_user_id',
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
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.notes'       => 'nullable|string'
        ]);


        foreach ($request->items as $index => $item) {
            $itemType = $item['item_type'];
            $itemId = $item['item_id'];

            if (!class_exists($itemType)) {
                return response()->json([
                    'message' => "نوع آیتم نامعتبر در ردیف {$index}: {$itemType}"
                ], 422);
            }

            if (!is_subclass_of($itemType, \Illuminate\Database\Eloquent\Model::class)) {
                return response()->json([
                    'message' => "مدل ارث‌برده از Eloquent نیست در ردیف {$index}: {$itemType}"
                ], 422);
            }

            $exists = $itemType::where('id', $itemId)->exists();
            if (!$exists) {
                return response()->json([
                    'message' => "آیتم با شناسه {$itemId} در مدل {$itemType} یافت نشد (ردیف {$index})."
                ], 422);
            }
        }

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
                'from_inventory_type' => ['طفاً حداقل یکی از «نوع انبار مبدأ» یا «کاربر مبدأ» را مشخص کنید.']
            ]);
        });

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
                    'طفاً حداقل یکی از «نوع انبار مقصد» یا «کاربر مقصد» را مشخص کنید.'
                ]
            ]);
        });

        $transfer = DB::transaction(function () use ($request, $fromInventory, $toInventory) {
            $transfer = Transfer::create([
                'from_user_id' => $request->from_user_id,
                'to_user_id' => $request->to_user_id,
                'from_inventory_id' => $fromInventory->id,
                'to_inventory_id' => $toInventory->id,
                'transfer_date' => $request->transfer_date,
                'description' => $request->description,
            ]);

            foreach ($request->items as $item) {
                $transfer->items()->create($item);
            }

            return $transfer;
        });

        $this->updateInventoryOnCreate($transfer);

        return $this->show($transfer->id);
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
            'items.item'
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
        return DB::transaction(function () use ($transfer) {
            $this->updateInventoryOnDelete($transfer);

            $transfer->items()->delete();
            $transfer->delete();

            return response()->json(null, 204);
        });
    }

    /**
     * Update inventory based on transfer items.
     */
    private function updateInventoryOnCreate(Transfer $transfer): void
    {
        foreach ($transfer->items as $item) {
            $strategy = StrategyResolver::resolve($item);

            // From inventory
            if ($transfer->from_inventory_id) {
                $fromItem = InventoryItem::firstOrCreate(
                    [
                        'inventory_id' => $transfer->from_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type
                    ]
                );

                $strategy->handleOutgoing($fromItem, $item->quantity);
            }

            // To inventory
            if ($transfer->to_inventory_id) {
                $toItem = InventoryItem::firstOrCreate(
                    [
                        'inventory_id' => $transfer->to_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type
                    ]
                );

                $strategy->handleIncoming($toItem, $item->quantity);
            }
        }
    }

    /**
     * Reverse inventory changes when transfer is deleted.
     */
    private function updateInventoryOnDelete(Transfer $transfer): void
    {
        foreach ($transfer->items as $item) {
            $strategy = StrategyResolver::resolve($item);

            // Reverse: from_inventory gets back what it gave
            if ($transfer->from_inventory_id) {
                $fromItem = InventoryItem::firstOrCreate(
                    [
                        'inventory_id' => $transfer->from_inventory_id,
                        'item_id' => $item->item_id,
                        'item_type' => $item->item_type
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
                        'item_type' => $item->item_type
                    ]
                );

                $strategy->reverseIncoming($toItem, $item->quantity);
            }
        }
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
