<?php

declare(strict_types=1);

namespace App\Entity;

enum ItemType: string
{
    case Folder = 'folder';
    case File = 'file';
}
