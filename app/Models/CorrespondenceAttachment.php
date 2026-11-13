<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CorrespondenceAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'correspondence_id',
        'uploaded_by_id',
        'disk',
        'storage_path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    protected $appends = [
        'path',
    ];

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function getPathAttribute(): ?string
    {
        if (!$this->storage_path) {
            return null;
        }

        $base = rtrim((string) config('filesystems.public_storage_path', ''), '/');
        $storagePath = ltrim((string) $this->storage_path, '/');

        if ($base !== '') {
            return $base . '/' . $storagePath;
        }

        $diskName = $this->disk ?: config('filesystems.default', 'local');
        $disk = Storage::disk($diskName);

        try {
            $url = $disk->url($this->storage_path);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        } catch (\Throwable $e) {
            // fall through to manual composition
        }

        $diskConfig = config("filesystems.disks.{$diskName}", []);
        $baseUrl = $diskConfig['url'] ?? config('app.url', '');
        $root = $diskConfig['root'] ?? $disk->path('');

        $parts = array_filter([rtrim((string) $baseUrl, '/'), trim((string) $root, '/'), $storagePath], fn ($part) => $part !== '');

        return implode('/', $parts);
    }
}
