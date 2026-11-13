<?php

namespace App\Enums\Correspondence;

enum RecipientType: string
{
    case TO = 'to';
    case CC = 'cc';
}
