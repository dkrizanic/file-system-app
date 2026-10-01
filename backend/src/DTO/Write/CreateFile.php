<?php

declare(strict_types=1);

namespace App\DTO\Write;

use App\Validator\ItemName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;

final class CreateFile
{
    public function __construct(
        #[Assert\Uuid]
        #[Assert\NotNull(message: 'The parent folder is required.')]
        public readonly ?Uuid $parentId = null,
        #[ItemName]
        public readonly string $name = '',
    ) {
    }

    public function parent(): Uuid
    {
        return $this->parentId ?? throw new \LogicException('CreateFile requires a parentId; the NotNull constraint rejects a missing one.');
    }
}
