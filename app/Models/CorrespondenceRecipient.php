<?php

namespace App\Models;

use App\Enums\Correspondence\RecipientType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'correspondence_id',
        'user_id',
        'type',
        'due_at',
        'read_at',
        'archived_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'correspondence_id' => 'integer',
        'user_id' => 'integer',
        'type' => RecipientType::class,
        'due_at' => 'datetime',
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
