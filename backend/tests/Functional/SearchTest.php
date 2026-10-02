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
    #[TestDox('A search name padded with spaces still matches the trimmed stored name')]
    public function paddedSearchNameMatchesTheTrimmedName(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $this->createFileAt($folder->id, 'notes.txt');

        $response = $this->request('GET', '/api/search?name=%20%20notes.txt%20%20&scope=all');

        self::assertSame(200, $response->getStatusCode());

        $page = $this->decode($response);
        self::assertSame(1, $page['total']);
        self::assertSame('notes.txt', $this->pageItems($page)[0]['name']);
    }

    #[Test]
    #[TestDox('A search name that is not valid UTF-8 is a 400, not a 500')]
    public function invalidUtf8SearchNameReturns400(): void
    {
        $response = $this->request('GET', '/api/search?name=%FF&scope=all');

        $error = $this->errorEnvelope($response, 400, 'validation_failed');
        self::assertSame('The search term must be valid UTF-8.', $this->detailFor($error, 'name'));
    }

    #[Test]
    #[TestDox('A suggestion prefix that is not valid UTF-8 is a 400, not a 500')]
    public function invalidUtf8SuggestionPrefixReturns400(): void
    {
        $response = $this->request('GET', '/api/suggestions?prefix=%FF');

        $error = $this->errorEnvelope($response, 400, 'validation_failed');
        self::assertSame('The search term must be valid UTF-8.', $this->detailFor($error, 'prefix'));
    }

    #[Test]
    #[TestDox('A folder-scoped search returns only the matches under that folder')]
    public function folderScopedSearchReturnsOnlyMatchesUnderTheFolder(): void
    {
        $work = $this->createFolderAt(null, 'Work');
        $this->createFileAt($work->id, 'notes.txt');
        $archive = $this->createFolderAt(null, 'Archive');
        $this->createFileAt($archive->id, 'notes.txt');

        $response = $this->request('GET', '/api/search?name=notes.txt&scope=folder&folderId='.$work->id->toRfc4122());

        self::assertSame(200, $response->getStatusCode());

        $page = $this->decode($response);
        self::assertSame(1, $page['total']);
        self::assertSame($work->id->toRfc4122(), $this->pageItems($page)[0]['parentId']);
    }

    #[Test]
    #[TestDox('A folder scope without a folderId is a 400 validation failure on folderId')]
    public function folderScopeWithoutFolderIdReturns400(): void
    {
        $response = $this->request('GET', '/api/search?name=notes.txt&scope=folder');

        $error = $this->errorEnvelope($response, 400, 'validation_failed');
        self::assertSame('A folder scope requires a folderId.', $this->detailFor($error, 'folderId'));
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
