<?php

declare(strict_types=1);

namespace App\DTO\Read;

use App\Entity\ItemType;
use Symfony\Component\Uid\Uuid;

final class ItemDetail
{
    /**
     * @param list<PathEntry> $parentPath
     */
    public function __construct(
        public readonly Uuid $id,
        public readonly ItemType $type,
        public readonly string $name,
        public readonly ?Uuid $parentId,
        public readonly array $parentPath,
    ) {
    }
}
