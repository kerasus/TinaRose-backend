<?php

namespace App\Enums\Correspondence;

enum CorrespondenceEventType: string
{
    case SENT = 'sent';
    case READ = 'read';
    case ASSIGNED = 'assigned';
    case STARTED = 'started';
    case REPLIED = 'replied';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case CLOSED = 'closed';
}
