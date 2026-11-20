<?php

namespace App\Models;

use App\Enums\Correspondence\CorrespondenceConfidentiality;
use App\Enums\Correspondence\CorrespondencePriority;
use App\Enums\Correspondence\CorrespondenceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Correspondence extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'sender_id',
        'thread_root_id',
        'reply_to_id',
        'subject',
        'body',
        'status',
        'priority',
        'confidentiality',
        'due_at',
        'sent_at',
        'closed_at',
    ];

    protected $appends = [
        'has_been_seen',
    ];

    protected $casts = [
        'id' => 'integer',
        'sender_id' => 'integer',
        'thread_root_id' => 'integer',
        'reply_to_id' => 'integer',
        'status' => CorrespondenceStatus::class,
        'priority' => CorrespondencePriority::class,
        'confidentiality' => CorrespondenceConfidentiality::class,
        'due_at' => 'datetime',
        'sent_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * آیا نامه توسط هیچ‌یک از گیرندگان یا مسئولان ارجاع مشاهده شده است؟
     */
    public function getHasBeenSeenAttribute(): bool
    {
        $recipientsSeen = $this->relationLoaded('recipients')
            ? $this->recipients->contains(fn (CorrespondenceRecipient $recipient) => $recipient->read_at !== null)
            : $this->recipients()->whereNotNull('read_at')->exists();

        if ($recipientsSeen) {
            return true;
        }

        return $this->relationLoaded('assignments')
            ? $this->assignments->contains(fn (CorrespondenceAssignment $assignment) => $assignment->read_at !== null)
            : $this->assignments()->whereNotNull('read_at')->exists();
    }

    public function threadRoot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'thread_root_id');
    }

    public function threadChildren(): HasMany
    {
        return $this->hasMany(self::class, 'thread_root_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CorrespondenceRecipient::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CorrespondenceAssignment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CorrespondenceEvent::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CorrespondenceAttachment::class);
    }
}
