<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ItemNameValidator extends ConstraintValidator
{
    private const RESERVED_NAMES = ['.', '..'];
    private const CONTROL_CHARACTERS = '/\p{Cc}/u';

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
            $this->reject($constraint->blankMessage);

            return;
        }

        $this->rejectWhenTooLong($name, $constraint);
        $this->rejectWhenReserved($name, $constraint);
        $this->rejectWhenContainingPathSeparator($name, $constraint);
        $this->rejectWhenContainingControlCharacter($name, $constraint);
    }

    private function reject(string $message): void
    {
        $this->context->buildViolation($message)->addViolation();
    }

    private function rejectWhenTooLong(string $name, ItemName $constraint): void
    {
        if (mb_strlen($name) > ItemName::MAX_LENGTH) {
            $this->context->buildViolation($constraint->tooLongMessage)
                ->setParameter('{{ limit }}', (string) ItemName::MAX_LENGTH)
                ->addViolation();
        }
    }

    private function rejectWhenReserved(string $name, ItemName $constraint): void
    {
        if (\in_array($name, self::RESERVED_NAMES, true)) {
            $this->reject($constraint->reservedMessage);
        }
    }

    private function rejectWhenContainingPathSeparator(string $name, ItemName $constraint): void
    {
        if (false !== strpbrk($name, '/\\')) {
            $this->reject($constraint->slashMessage);
        }
    }

    private function rejectWhenContainingControlCharacter(string $name, ItemName $constraint): void
    {
        if (1 === preg_match(self::CONTROL_CHARACTERS, $name)) {
            $this->reject($constraint->controlCharMessage);
        }
    }
}
