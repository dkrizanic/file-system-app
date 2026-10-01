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

        if (null === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $name = trim($value);

        if ('' === $name) {
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

        if (false !== strpbrk($name, '/\\')) {
            $this->context->buildViolation($constraint->slashMessage)->addViolation();
        }

        if (1 === preg_match('/\p{Cc}/u', $name)) {
            $this->context->buildViolation($constraint->controlCharMessage)->addViolation();
        }
    }
}
