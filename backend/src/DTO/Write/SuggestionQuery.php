<?php

declare(strict_types=1);

namespace App\DTO\Write;

final class SuggestionQuery
{
    public function __construct(
        public readonly string $prefix = '',
    ) {
    }
}
