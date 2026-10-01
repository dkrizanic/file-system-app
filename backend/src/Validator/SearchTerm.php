<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class SearchTerm extends Constraint
{
    public string $blankMessage = 'The search term must not be blank.';

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
