<?php

declare(strict_types=1);

namespace App\DTO\Write;

use Symfony\Component\Validator\Constraints as Assert;

final class PaginationQuery
{
    public function __construct(
        #[Assert\Range(min: 1, max: 100, notInRangeMessage: 'The page size must be between {{ min }} and {{ max }}.')]
        public readonly int $limit = 50,
        #[Assert\PositiveOrZero(message: 'The page offset must not be negative.')]
        public readonly int $offset = 0,
    ) {
    }
}
