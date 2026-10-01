<?php

declare(strict_types=1);

namespace App\DTO\Read;

use Symfony\Component\Uid\Uuid;

final class Suggestion
{
    /**
     * @param list<PathEntry> $parentPath
     */
    public function __construct(
        public readonly Uuid $id,
        public readonly string $name,
        public readonly array $parentPath,
    ) {
    }
}
