<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ItemName extends Constraint
{
    public const MAX_LENGTH = 255;

    public string $blankMessage = 'Name must not be blank.';
    public string $tooLongMessage = 'Name must not be longer than {{ limit }} characters.';
    public string $reservedMessage = 'Name must not be "." or "..".';
    public string $slashMessage = 'Name must not contain "/" or "\\".';
    public string $controlCharMessage = 'Name must not contain control characters.';

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
