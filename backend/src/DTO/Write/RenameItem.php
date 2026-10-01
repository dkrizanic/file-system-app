<?php

declare(strict_types=1);

namespace App\DTO\Write;

use App\Validator\ItemName;

final class RenameItem
{
    public function __construct(
        #[ItemName]
        public readonly string $name = '',
    ) {
    }
}
