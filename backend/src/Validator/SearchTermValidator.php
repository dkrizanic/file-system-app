<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class SearchTermValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SearchTerm) {
            throw new \InvalidArgumentException(\sprintf('Expected an instance of %s, got %s.', SearchTerm::class, get_debug_type($constraint)));
        }

        if ($value === null) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (trim($value) === '') {
            $this->context->buildViolation($constraint->blankMessage)->addViolation();
        }
    }
}
