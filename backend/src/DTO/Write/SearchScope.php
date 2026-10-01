<?php

declare(strict_types=1);

namespace App\DTO\Write;

enum SearchScope: string
{
    case Folder = 'folder';
    case All = 'all';
}
