<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

final class SearchTest extends ApiTestCase
{
    #[Test]
    #[TestDox('An exact, case-insensitive search across all folders returns the file with its path')]
    public function exactSearchAcrossAllFoldersReturnsMatches(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $this->createFileAt($folder->id, '2026.pdf');

        $response = $this->request('GET', '/api/search?name=2026.PDF&scope=all');

        self::assertSame(200, $response->getStatusCode());

        $page = $this->decode($response);
        self::assertSame(1, $page['total']);
        $match = $this->pageItems($page)[0];
        self::assertSame('2026.pdf', $match['name']);
        self::assertSame(
            [
                ['id' => $this->rootId()->toRfc4122(), 'name' => 'Root'],
                ['id' => $folder->id->toRfc4122(), 'name' => 'Work'],
            ],
            $match['parentPath'],
        );
    }

    #[Test]
    #[TestDox('A blank search name is a 400 validation failure on name')]
    public function blankSearchNameReturns400(): void
    {
        $response = $this->request('GET', '/api/search?name=%20%20&scope=all');

        $error = $this->errorEnvelope($response, 400, 'validation_failed');
        self::assertSame('The search term must not be blank.', $this->detailFor($error, 'name'));
    }

    #[Test]
    #[TestDox('Suggestions list files starting with the prefix, folders excluded, with their path')]
    public function suggestionsListFilesStartingWithPrefix(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $this->createFolderAt($folder->id, 'report archive');
        $this->createFileAt($folder->id, 'report draft.txt');

        $response = $this->request('GET', '/api/suggestions?prefix=Repo');

        self::assertSame(200, $response->getStatusCode());

        $page = $this->decode($response);
        $suggestions = $this->pageItems($page);
        self::assertSame(['items'], array_keys($page));
        self::assertCount(1, $suggestions);

        $suggestion = $suggestions[0];
        self::assertSame('report draft.txt', $suggestion['name']);
        self::assertSame(
            [
                ['id' => $this->rootId()->toRfc4122(), 'name' => 'Root'],
                ['id' => $folder->id->toRfc4122(), 'name' => 'Work'],
            ],
            $suggestion['parentPath'],
        );
    }
}
