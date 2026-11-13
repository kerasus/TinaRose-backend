<?php

namespace App\Models;

use App\Enums\Correspondence\AssignmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorrespondenceAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'correspondence_id',
        'parent_assignment_id',
        'assigned_by_id',
        'assigned_to_id',
        'note',
        'status',
        'due_at',
        'read_at',
        'started_at',
        'resolved_at',
        'resolution_note',
        'archived_at',
    ];

    protected $casts = [
        'status' => AssignmentStatus::class,
        'due_at' => 'datetime',
        'read_at' => 'datetime',
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_assignment_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_assignment_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CorrespondenceEvent::class, 'assignment_id');
    }
}
