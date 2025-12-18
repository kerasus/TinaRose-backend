<?php

namespace App\Services\Transfer;

use App\Models\Inventory;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TransferInventoryResolver
{
    public function resolveFromInventory(array $data, array $validTypes): ?Inventory
    {
        if ($data['from_inventory_type'] || $data['from_user_id']) {
            if ($data['from_inventory_type'] && in_array($data['from_inventory_type'], $validTypes)) {
                if ($data['from_inventory_type'] !== 'assembler') {
                    return Inventory::firstOrCreate(
                        ['type' => $data['from_inventory_type']],
                        [
                            'name' => $this->getInventoryNameByType($data['from_inventory_type']),
                            'description' => "انبار عمومی - {$data['from_inventory_type']}",
                            'user_id' => null,
                            'type' => $data['from_inventory_type']
                        ]
                    );
                }

                if (!$data['from_user_id']) {
                    throw ValidationException::withMessages([
                        'from_user_id' => ['برای انبار نوع مونتاژکار باید کاربر مبدأ مشخص شود.']
                    ]);
                }

                $user = User::findOrFail($data['from_user_id']);
                return $this->getOrCreateUserInventory($user);
            }

            if ($data['from_user_id']) {
                return $this->getOrCreateUserInventory(User::findOrFail($data['from_user_id']));
            }

            throw ValidationException::withMessages([
                'from_inventory_type' => ['لطفاً حداقل یکی از «نوع انبار مبدأ» یا «کاربر مبدأ» را مشخص کنید.']
            ]);
        }

        return null;
    }

    public function resolveToInventory(array $data, array $validTypes): ?Inventory
    {
        if ($data['to_inventory_type'] && !$data['to_user_id']) {
            throw ValidationException::withMessages([
                'to_user_id' => ['برای انبار مقصد، کاربر گیرنده الزامی است.']
            ]);
        }

        if ($data['to_inventory_type'] || $data['to_user_id']) {
            if ($data['to_inventory_type'] && in_array($data['to_inventory_type'], $validTypes)) {
                if ($data['to_inventory_type'] !== 'assembler') {
                    return Inventory::firstOrCreate(
                        ['type' => $data['to_inventory_type']],
                        [
                            'name' => $this->getInventoryNameByType($data['to_inventory_type']),
                            'description' => "انبار عمومی - {$data['to_inventory_type']}",
                            'user_id' => null,
                            'type' => $data['to_inventory_type']
                        ]
                    );
                }

                if (!$data['to_user_id']) {
                    throw ValidationException::withMessages([
                        'to_user_id' => ['برای انبار نوع "assembler" باید کاربر مقصد مشخص شود.']
                    ]);
                }

                $user = User::findOrFail($data['to_user_id']);
                return $this->getOrCreateUserInventory($user);
            }

            if ($data['to_user_id']) {
                return $this->getOrCreateUserInventory(User::findOrFail($data['to_user_id']));
            }

            throw ValidationException::withMessages([
                'to_inventory_type' => [
                    'لطفاً حداقل یکی از «نوع انبار مقصد» یا «کاربر مقصد» را مشخص کنید.'
                ]
            ]);
        }

        return null;
    }

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
}
