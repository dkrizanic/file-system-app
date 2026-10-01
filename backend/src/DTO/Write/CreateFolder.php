<?php

declare(strict_types=1);

namespace App\DTO\Write;

use App\Validator\ItemName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;

final class CreateFolder
{
    public function __construct(
        #[Assert\Uuid]
        public readonly ?Uuid $parentId = null,
        #[ItemName]
        public readonly string $name = '',
    ) {
    }
}
