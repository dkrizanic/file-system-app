<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class SearchTerm extends Constraint
{
    public string $blankMessage = 'The search term must not be blank.';
    public string $encodingMessage = 'The search term must be valid UTF-8.';

    /** A blank suggestion prefix is a valid query that matches nothing, so it stays allowed there. */
    public bool $allowBlank = false;

    public function __construct(
        bool $allowBlank = false,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->allowBlank = $allowBlank;
    }

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
