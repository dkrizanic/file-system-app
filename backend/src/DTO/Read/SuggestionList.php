<?php

declare(strict_types=1);

namespace App\DTO\Read;

final class SuggestionList
{
    /**
     * @param list<Suggestion> $items
     */
    public function __construct(
        public readonly array $items,
    ) {
    }
}
