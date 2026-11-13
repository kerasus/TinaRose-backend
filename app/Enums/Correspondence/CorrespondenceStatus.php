<?php

namespace App\Enums\Correspondence;

enum CorrespondenceStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case CANCELLED = 'cancelled';
}
