<?php

namespace App\Enums\Correspondence;

enum CorrespondencePriority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';
}
