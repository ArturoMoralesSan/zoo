<?php

namespace App\Enums;

enum CardTransferType: string
{
    case Export = 'export';
    case Import = 'import';
}
