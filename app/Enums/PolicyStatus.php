<?php

namespace App\Enums;

enum PolicyStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
