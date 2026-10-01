<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

final class SuggestionsEndpointTest extends ApiTestCase
{
    #[Test]
    #[TestDox('Suggestions return at most ten files ordered by normalized name, each with its path')]
    public function suggestions_return_top_files_ordered_with_paths(): void
    {
        $folder = $this->createFolderAt(null, 'reports');
        $expected = [];
        foreach (range(1, 30) as $i) {
            $file = $this->createFileAt($folder->id, sprintf('rep-%02d', $i));
            if ($i <= 10) {
                $expected[] = $file->id->toRfc4122();
            }
        }
        $this->createFolderAt($folder->id, 'rep-folder');

        $payload = $this->decode($this->request('GET', '/api/suggestions?prefix=Rep'));

        self::assertSame(['items'], array_keys($payload));
        self::assertSame($expected, array_column($payload['items'], 'id'));
        self::assertSame('rep-01', $payload['items'][0]['name']);

        foreach ($payload['items'] as $suggestion) {
            self::assertSame(['id', 'name', 'parentPath'], array_keys($suggestion));
            self::assertSame(
                [
                    ['id' => $this->rootId()->toRfc4122(), 'name' => 'Root'],
                    ['id' => $folder->id->toRfc4122(), 'name' => 'reports'],
                ],
                $suggestion['parentPath'],
            );
        }
    }

    #[Test]
    #[TestDox('Suggestions match by prefix, not substring')]
    public function suggestions_use_prefix_matching_only(): void
    {
        $folder = $this->createFolderAt(null, 'A');
        $this->createFileAt($folder->id, 'invoices-2026');
        $this->createFileAt($folder->id, 'old-invoices');

        $payload = $this->decode($this->request('GET', '/api/suggestions?prefix=INVOICES'));

        self::assertCount(1, $payload['items']);
        self::assertSame('invoices-2026', $payload['items'][0]['name']);
    }

    #[Test]
    #[TestDox('LIKE wildcards in the prefix match literally')]
    public function like_wildcards_match_literally(): void
    {
        $folder = $this->createFolderAt(null, 'A');
        $underscore = $this->createFileAt($folder->id, 'a_b');
        $this->createFileAt($folder->id, 'axb');
        $percent = $this->createFileAt($folder->id, 'a%c');

        $byUnderscore = $this->decode($this->request('GET', '/api/suggestions?prefix=a_'));
        $byPercent = $this->decode($this->request('GET', '/api/suggestions?prefix=a%25'));

        self::assertSame([$underscore->id->toRfc4122()], array_column($byUnderscore['items'], 'id'));
        self::assertSame([$percent->id->toRfc4122()], array_column($byPercent['items'], 'id'));
    }

    #[Test]
    #[TestDox('A blank or missing prefix returns an empty suggestion list')]
    public function blank_prefix_returns_empty_list(): void
    {
        $folder = $this->createFolderAt(null, 'A');
        $this->createFileAt($folder->id, 'report');

        $blank = $this->decode($this->request('GET', '/api/suggestions?prefix='));
        $missing = $this->decode($this->request('GET', '/api/suggestions'));

        self::assertSame(['items'], array_keys($blank));
        self::assertSame([], $blank['items']);
        self::assertSame([], $missing['items']);
    }

    #[Test]
    #[TestDox('Zero suggestions are an empty list, never an error')]
    public function zero_suggestions_return_empty_list(): void
    {
        $payload = $this->decode($this->request('GET', '/api/suggestions?prefix=zzz'));

        self::assertSame([], $payload['items']);
    }

    #[Test]
    #[TestDox('Suggestions run in a single query')]
    public function suggestions_use_one_query(): void
    {
        $folder = $this->createFolderAt(null, 'A');
        $this->createFileAt($folder->id, 'report');

        $this->queryCounter()->reset();
        $response = $this->request('GET', '/api/suggestions?prefix=rep');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, count($this->queryCounter()));
    }
}
