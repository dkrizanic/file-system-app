<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

final class SearchEndpointTest extends ApiTestCase
{
    #[Test]
    #[TestDox('An all-scope search finds files case-insensitively across folders, never folders')]
    public function allScopeSearchFindsFilesWithPaths(): void
    {
        $folderA = $this->createFolderAt(null, 'A');
        $folderB = $this->createFolderAt(null, 'B');
        $inA = $this->createFileAt($folderA->id, 'Notes');
        $inB = $this->createFileAt($folderB->id, 'notes');
        $this->createFolderAt(null, 'notes');

        $page = $this->decode($this->request('GET', '/api/search?name=NOTES&scope=all'));
        $items = $this->pageItems($page);

        self::assertSame(2, $page['total']);
        self::assertSame(50, $page['limit']);
        self::assertSame(0, $page['offset']);
        self::assertSame(
            [$inA->id->toRfc4122(), $inB->id->toRfc4122()],
            $this->sortedIds(array_column($items, 'id')),
        );

        foreach ($items as $item) {
            self::assertSame(['id', 'type', 'name', 'parentId', 'parentPath'], array_keys($item));
        }

        $indexOfInA = array_search($inA->id->toRfc4122(), array_column($items, 'id'), true);
        self::assertIsInt($indexOfInA);
        $inADetail = $items[$indexOfInA];
        self::assertSame(
            [['id' => $this->rootId()->toRfc4122(), 'name' => 'Root'], ['id' => $folderA->id->toRfc4122(), 'name' => 'A']],
            $inADetail['parentPath'],
        );
    }

    #[Test]
    #[TestDox('A folder scope restricts the search to that folder subtree')]
    public function folderScopeRestrictsToSubtree(): void
    {
        $scope = $this->createFolderAt(null, 'Scope');
        $nested = $this->createFolderAt($scope->id, 'Nested');
        $inside = $this->createFileAt($nested->id, 'dup');
        $outside = $this->createFolderAt(null, 'Outside');
        $this->createFileAt($outside->id, 'dup');

        $page = $this->decode($this->request('GET', '/api/search?name=dup&scope=folder&folderId='.$scope->id->toRfc4122()));
        $items = $this->pageItems($page);

        self::assertSame(1, $page['total']);
        self::assertSame($inside->id->toRfc4122(), $items[0]['id']);
        self::assertSame('dup', $items[0]['name']);
    }

    #[Test]
    #[TestDox('Searching with the root as scope behaves like the all scope')]
    public function rootScopeBehavesLikeAll(): void
    {
        $folder = $this->createFolderAt(null, 'A');
        $this->createFileAt($folder->id, 'dup');
        $this->createFileAt($this->rootId(), 'dup');

        $rootPage = $this->decode($this->request('GET', '/api/search?name=dup&scope=folder&folderId='.$this->rootId()->toRfc4122()));
        $allPage = $this->decode($this->request('GET', '/api/search?name=dup&scope=all'));

        self::assertSame(2, $rootPage['total']);
        self::assertSame($allPage['total'], $rootPage['total']);
        self::assertSame(
            array_column($this->pageItems($allPage), 'id'),
            array_column($this->pageItems($rootPage), 'id'),
        );
    }

    #[Test]
    #[TestDox('The all scope ignores a folderId, even a bogus one')]
    public function allScopeIgnoresFolderId(): void
    {
        $folder = $this->createFolderAt(null, 'A');
        $file = $this->createFileAt($folder->id, 'dup');

        $page = $this->decode($this->request('GET', '/api/search?name=dup&scope=all&folderId=01890a5d-ac96-774b-bcce-b302099a8057'));

        self::assertSame(1, $page['total']);
        self::assertSame($file->id->toRfc4122(), $this->pageItems($page)[0]['id']);
    }

    #[Test]
    #[TestDox('Blank or missing search names are 400 validation failures')]
    public function blankOrMissingNameReturns400(): void
    {
        $error = $this->errorEnvelope($this->request('GET', '/api/search?name=%20&scope=all'), 400, 'validation_failed');
        self::assertSame('The search term must not be blank.', $this->detailFor($error, 'name'));

        $error = $this->errorEnvelope($this->request('GET', '/api/search'), 400, 'validation_failed');
        self::assertNotSame('', $this->detailFor($error, 'name'));
    }

    #[Test]
    #[TestDox('A folder scope without folderId is a 400 with a folderId detail')]
    public function folderScopeWithoutFolderIdReturns400(): void
    {
        $error = $this->errorEnvelope($this->request('GET', '/api/search?name=x&scope=folder'), 400, 'validation_failed');

        self::assertSame('A folder scope requires a folderId.', $this->detailFor($error, 'folderId'));
    }

    #[Test]
    #[TestDox('A nonexistent folder scope is a 404')]
    public function nonexistentFolderScopeReturns404(): void
    {
        $this->errorEnvelope(
            $this->request('GET', '/api/search?name=x&scope=folder&folderId=01890a5d-ac96-774b-bcce-b302099a8057'),
            404,
            'not_found',
        );
    }

    #[Test]
    #[TestDox('A file used as a folder scope is a 404')]
    public function fileFolderScopeReturns404(): void
    {
        $folder = $this->createFolderAt(null, 'A');
        $file = $this->createFileAt($folder->id, 'notes.txt');

        $this->errorEnvelope(
            $this->request('GET', '/api/search?name=x&scope=folder&folderId='.$file->id->toRfc4122()),
            404,
            'not_found',
        );
    }

    #[Test]
    #[TestDox('An unknown scope value is a 400 with a scope detail')]
    public function unknownScopeReturns400(): void
    {
        $error = $this->errorEnvelope($this->request('GET', '/api/search?name=x&scope=everywhere'), 400, 'validation_failed');

        self::assertNotSame('', $this->detailFor($error, 'scope'));
    }

    #[Test]
    #[TestDox('Zero matches are an empty page, never an error')]
    public function zeroMatchesReturnEmptyPage(): void
    {
        $page = $this->decode($this->request('GET', '/api/search?name=missing&scope=all'));

        self::assertSame([], $page['items']);
        self::assertSame(0, $page['total']);
    }

    #[Test]
    #[TestDox('Search pages respect limit and offset with a full total')]
    public function searchPaginatesWithFullTotal(): void
    {
        $folder = $this->createFolderAt(null, 'A');
        $this->createFileAt($folder->id, 'dup');
        $this->createFileAt($this->rootId(), 'dup');

        $pageOne = $this->decode($this->request('GET', '/api/search?name=dup&scope=all&limit=1'));
        $pastEnd = $this->decode($this->request('GET', '/api/search?name=dup&scope=all&limit=1&offset=5'));

        self::assertSame(2, $pageOne['total']);
        self::assertCount(1, $this->pageItems($pageOne));
        self::assertSame([], $pastEnd['items']);
        self::assertSame(2, $pastEnd['total']);
    }

    #[Test]
    #[TestDox('Search runs a constant number of queries in both scopes')]
    public function searchUsesConstantQueries(): void
    {
        $folder = $this->createFolderAt(null, 'A');
        $this->createFileAt($folder->id, 'dup');
        $this->createFileAt($this->rootId(), 'dup');
        $this->entityManager()->clear();

        $this->queryCounter()->reset();
        $this->request('GET', '/api/search?name=dup&scope=all');
        self::assertSame(1, count($this->queryCounter()));

        $this->queryCounter()->reset();
        $this->request('GET', '/api/search?name=dup&scope=folder&folderId='.$folder->id->toRfc4122());
        self::assertSame(2, count($this->queryCounter()));
    }

    /**
     * @param list<mixed> $ids
     *
     * @return list<mixed>
     */
    private function sortedIds(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}
