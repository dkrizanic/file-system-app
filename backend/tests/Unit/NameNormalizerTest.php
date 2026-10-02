<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\NameNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class NameNormalizerTest extends TestCase
{
    private NameNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new NameNormalizer();
    }

    #[Test]
    #[TestDox('Case-folds names so differently-cased spellings collide')]
    public function caseFoldsNamesToTheirCollidingForm(): void
    {
        self::assertSame('notes', $this->normalizer->normalize('Notes'));
        self::assertSame('ćwórd', $this->normalizer->normalize('ĆWÓRD'));
    }

    #[Test]
    #[TestDox('A name that is not valid UTF-8 is rejected')]
    public function invalidUtf8NameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->normalizer->normalize("\xC3\x28");
    }
}
