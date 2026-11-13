<?php

namespace App\Models;

use App\Enums\Correspondence\CorrespondenceEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'correspondence_id',
        'assignment_id',
        'actor_id',
        'event_type',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'event_type' => CorrespondenceEventType::class,
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceAssignment::class, 'assignment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
