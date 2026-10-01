<?php

declare(strict_types=1);

namespace App\DTO\Read;

use Symfony\Component\Uid\Uuid;

final class PathEntry
{
    public function __construct(
        public readonly Uuid $id,
        public readonly string $name,
    ) {
    }
}
