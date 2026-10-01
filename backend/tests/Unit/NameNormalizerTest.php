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
    #[TestDox('Lowercases ASCII letters')]
    public function lowercases_ascii_letters(): void
    {
        self::assertSame('notes', $this->normalizer->normalize('Notes'));
    }

    #[Test]
    #[TestDox('Case-folds multibyte letters')]
    public function case_folds_multibyte_letters(): void
    {
        self::assertSame('ćwórd', $this->normalizer->normalize('ĆWÓRD'));
    }

    #[Test]
    #[TestDox('Final-sigma variants of one name fold to the same normal form')]
    public function folds_final_sigma_variants_to_one_form(): void
    {
        $uppercased = $this->normalizer->normalize("\u{039F}\u{0394}\u{038C}\u{03A3}");
        $withFinalSigma = $this->normalizer->normalize("\u{03BF}\u{03B4}\u{03CC}\u{03C2}");
        $withPlainSigma = $this->normalizer->normalize("\u{03BF}\u{03B4}\u{03CC}\u{03C3}");

        self::assertSame("\u{03BF}\u{03B4}\u{03CC}\u{03C3}", $uppercased);
        self::assertSame($uppercased, $withFinalSigma);
        self::assertSame($uppercased, $withPlainSigma);
    }

    #[Test]
    #[TestDox('Composes decomposed sequences to NFC')]
    public function composes_decomposed_sequences_to_nfc(): void
    {
        self::assertSame("\u{00E1}bc", $this->normalizer->normalize("a\u{0301}bc"));
    }

    #[Test]
    #[TestDox('Composes what case folding left decomposed')]
    public function composes_what_case_folding_left_decomposed(): void
    {
        self::assertSame("\u{00E1}rchive", $this->normalizer->normalize("A\u{0301}rchive"));
    }

    #[Test]
    #[TestDox('Leaves an empty name empty')]
    public function leaves_an_empty_name_empty(): void
    {
        self::assertSame('', $this->normalizer->normalize(''));
    }

    #[Test]
    #[TestDox('Normalizing twice changes nothing')]
    public function normalizing_twice_changes_nothing(): void
    {
        $name = "\u{0130}mp\u{00E9}rial A\u{0301}bc";

        $once = $this->normalizer->normalize($name);

        self::assertSame($once, $this->normalizer->normalize($once));
    }
}
