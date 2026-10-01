<?php

declare(strict_types=1);

namespace App\DTO\Read;

/**
 * @template TItem
 */
final class Page
{
    /**
     * @param list<TItem> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $limit,
        public readonly int $offset,
    ) {
    }
}
