<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ItemNameValidator extends ConstraintValidator
{
    private const RESERVED_NAMES = ['.', '..'];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ItemName) {
            throw new \InvalidArgumentException(\sprintf('Expected an instance of %s, got %s.', ItemName::class, get_debug_type($constraint)));
        }

        if ($value === null) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $name = trim($value);

        if ($name === '') {
            $this->context->buildViolation($constraint->blankMessage)->addViolation();

            return;
        }

        if (mb_strlen($name) > ItemName::MAX_LENGTH) {
            $this->context->buildViolation($constraint->tooLongMessage)
                ->setParameter('{{ limit }}', (string) ItemName::MAX_LENGTH)
                ->addViolation();
        }

        if (\in_array($name, self::RESERVED_NAMES, true)) {
            $this->context->buildViolation($constraint->reservedMessage)->addViolation();
        }

        if (strpbrk($name, '/\\') !== false) {
            $this->context->buildViolation($constraint->slashMessage)->addViolation();
        }

        if (preg_match('/\p{Cc}/u', $name) === 1) {
            $this->context->buildViolation($constraint->controlCharMessage)->addViolation();
        }
    }
}
