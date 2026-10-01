<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\String\UnicodeString;

final class NameNormalizer
{
    public function normalize(string $name): string
    {
        if (false === \Normalizer::normalize($name)) {
            throw new \InvalidArgumentException('A name cannot be normalized because it is not valid UTF-8.');
        }

        // Full case folding, not mere lowercasing: it collapses pairs that
        // Unicode lowercasing leaves distinct — final sigma "ΟΔΌΣ" vs "οδός" —
        // so visually equal names share one normal form. symfony/string's
        // folded() maps the fold pairs, but it lowercases afterwards and
        // mbstring re-introduces a contextual final ς from uppercase Σ, so
        // every remaining ς folds to σ here. Folding can emit decomposed
        // sequences (e.g. "İ" folds to "i" + combining dot), so NFC must run
        // last for the value to be canonical.
        $folded = str_replace(
            "\u{03C2}",
            "\u{03C3}",
            (new UnicodeString($name))->folded(false)->toString(),
        );

        $normalized = \Normalizer::normalize($folded, \Normalizer::FORM_C);

        if (!\is_string($normalized)) {
            throw new \InvalidArgumentException('A name cannot be normalized because it is not valid UTF-8.');
        }

        return $normalized;
    }
}
