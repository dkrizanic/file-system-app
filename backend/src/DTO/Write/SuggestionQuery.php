<?php

declare(strict_types=1);

namespace App\DTO\Write;

use App\Validator\SearchTerm;

final class SuggestionQuery
{
    public function __construct(
        #[SearchTerm(allowBlank: true)]
        public readonly string $prefix = '',
    ) {
    }
}
