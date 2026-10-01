<?php

declare(strict_types=1);

namespace App\DTO\Write;

use App\Validator\ItemName;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

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
