<?php

namespace App\Services\Transfer;

use App\Models\Transfer;
use App\Enums\TransferStatusType;
use Illuminate\Validation\ValidationException;

class TransferUpdateService
{
    private TransferValidationService $validationService;

    public function __construct(
        TransferValidationService $validationService,
    ) {
        $this->validationService = $validationService;
    }

    /**
     * @throws ValidationException
     */
    public function update(Transfer $transfer, array $data): Transfer
    {
        if ($transfer->status !== TransferStatusType::Pending) {
            throw ValidationException::withMessages([
                'transfer_status' => ['فقط حواله‌های در انتظار تأیید قابل ویرایش هستند.']
            ]);
        }

        $currentUser = auth()->user();
        if (
            $transfer->from_user_id !== $currentUser->id &&
            $transfer->to_user_id !== $currentUser->id &&
            $transfer->creator_user_id !== $currentUser->id
        ) {
            throw ValidationException::withMessages([
                'access_denied' => ['فقط فرستنده یا گیرنده یا سازنده این حواله می‌تواند آن را ویرایش کند.']
            ]);
        }

        if (isset($data['items']) && is_array($data['items']) && count($data['items']) > 0) {
            $this->validationService->validateItems($data['items']);
        }

        $transfer->update([
            'transfer_date' => $data['transfer_date'] ?? $transfer->transfer_date,
            'description' => $data['description'] ?? $transfer->description,
        ]);

        if (isset($data['items'])) {
            $transfer->items()->delete();

            foreach ($data['items'] as $item) {
                $transfer->items()->create($item);
            }
        }

        return $transfer;
    }
}
