<?php

namespace App\Services\Correspondence;

use App\Enums\Correspondence\CorrespondenceStatus;
use App\Models\Correspondence;
use App\Models\CorrespondenceAttachment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AttachmentService
{
    public function store(Correspondence $correspondence, UploadedFile $file, User $actor): CorrespondenceAttachment
    {
        if ($correspondence->sender_id !== $actor->id || $correspondence->status !== CorrespondenceStatus::DRAFT) {
            throw new AuthorizationException('پیوست فقط توسط فرستنده و در حالت پیش‌نویس قابل افزودن است.');
        }

        return DB::transaction(function () use ($correspondence, $file, $actor) {
            $disk = 'public';
            $path = $file->store('correspondence-attachments/' . $correspondence->id, $disk);

            return CorrespondenceAttachment::create([
                'correspondence_id' => $correspondence->id,
                'uploaded_by_id' => $actor->id,
                'disk' => $disk,
                'storage_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?? 0,
            ]);
        });
    }

    public function delete(CorrespondenceAttachment $attachment, User $actor): void
    {
        $correspondence = $attachment->correspondence;
        if ($correspondence->sender_id !== $actor->id || $correspondence->status !== CorrespondenceStatus::DRAFT) {
            throw new AuthorizationException('حذف پیوست فقط توسط فرستنده و در حالت پیش‌نویس مجاز است.');
        }

        DB::transaction(function () use ($attachment) {
            Storage::disk($attachment->disk)->delete($attachment->storage_path);
            $attachment->delete();
        });
    }
}
