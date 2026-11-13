<?php

namespace App\Enums\Correspondence;

enum CorrespondenceConfidentiality: string
{
    case INTERNAL = 'internal';
    case CONFIDENTIAL = 'confidential';
}
