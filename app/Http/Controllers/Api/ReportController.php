<?php

namespace App\Http\Controllers\Api;

use App\Models\Transfer;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Enums\TransferStatusType;
use App\Http\Controllers\Controller;

class ReportController extends Controller
{
    public function getPendingTransfersCountForUser(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = Transfer::with([
            'fromUser:id,firstname,lastname,username',
            'toUser:id,firstname,lastname,username',
            'fromInventory:id,name,type',
            'toInventory:id,name,type',
            'items.item:id,name',
            'items.color:id,name,color_hex'
        ])
            ->where('status', 'pending')
            ->where('to_user_id', $user->id)
            ->count();

        return response()->json($count, 200);
    }

    public function getAllPendingTransfersCount(Request $request): JsonResponse
    {
        // فقط ادمین‌ها می‌تونن این رو ببینن
        if (!$request->user()->hasRole('Manager')) {
            return $this->jsonResponseForbidden('دسترسی غیرمجاز', [
                'access' => 'شما دسترسی لازم را ندارید'
            ]);
        }

        $count = Transfer::with([
            'fromUser:id,firstname,lastname,username',
            'toUser:id,firstname,lastname,username',
            'fromInventory:id,name,type',
            'toInventory:id,name,type',
            'items.item:id,name',
            'items.color:id,name,color_hex'
        ])
            ->where('status', 'pending')
            ->count();

        return response()->json($count, 200);
    }

    public function getLockedInventoriesCount(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('Manager')) {
            return $this->jsonResponseForbidden('دسترسی غیرمجاز', [
                'access' => 'شما دسترسی لازم را ندارید'
            ]);
        }

        $count = Inventory::whereHas('inventoryCounts', function ($q) {
            $q->where('is_locked', false); // انبارگردانی باز
        })
            ->orWhereHas('transfersAsFrom', function ($q) {
                $q->where('status', TransferStatusType::Pending);
            })
            ->orWhereHas('transfersAsTo', function ($q) {
                $q->where('status', TransferStatusType::Pending);
            })
            ->count();

        return response()->json($count, 200);
    }

    private function jsonResponseForbidden($message, $messages): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => $messages
        ], 422);
    }
}
