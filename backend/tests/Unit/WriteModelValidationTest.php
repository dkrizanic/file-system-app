<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\DTO\Write\CreateFolder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class WriteModelValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    #[Test]
    #[TestDox('A well-formed folder name passes validation')]
    public function wellFormedFolderNamePasses(): void
    {
        $violations = $this->validator->validate(new CreateFolder(null, ' Projects '));

        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getPropertyPath().': '.$violation->getMessage();
        }

        self::assertSame([], $messages);
    }

    #[Test]
    #[TestDox('A blank folder name is rejected on name')]
    public function blankFolderNameIsRejected(): void
    {
        $violations = $this->validator->validate(new CreateFolder(null, '   '));

        self::assertCount(1, $violations);
        self::assertSame('name', $violations->get(0)->getPropertyPath());
        self::assertSame('Name must not be blank.', $violations->get(0)->getMessage());
    }
}
