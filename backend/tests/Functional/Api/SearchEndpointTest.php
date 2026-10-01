<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Uid\Uuid;

final class SearchEndpointTest extends ApiTestCase
{
    #[Test]
    #[TestDox('An all-scope search finds files case-insensitively across folders, never folders')]
    public function all_scope_search_finds_files_with_paths(): void
    {
        $folderA = $this->createFolderAt(null, 'A');
        $folderB = $this->createFolderAt(null, 'B');
        $inA = $this->createFileAt($folderA->id, 'Notes');
        $inB = $this->createFileAt($folderB->id, 'notes');
        $this->createFolderAt(null, 'notes');

        $page = $this->decode($this->request('GET', '/api/search?name=NOTES&scope=all'));

        self::assertSame(2, $page['total']);
        self::assertSame(50, $page['limit']);
        self::assertSame(0, $page['offset']);
        self::assertSame(
            [$inA->id->toRfc4122(), $inB->id->toRfc4122()],
            $this->sortedIds(array_column($page['items'], 'id')),
        );

        foreach ($page['items'] as $item) {
            self::assertSame(['id', 'type', 'name', 'parentId', 'parentPath'], array_keys($item));
        }

        $inADetail = $page['items'][array_search($inA->id->toRfc4122(), array_column($page['items'], 'id'), true)];
        self::assertSame(
            [['id' => $this->rootId()->toRfc4122(), 'name' => 'Root'], ['id' => $folderA->id->toRfc4122(), 'name' => 'A']],
            $inADetail['parentPath'],
        );
    }

    #[Test]
    #[TestDox('A folder scope restricts the search to that folder subtree')]
    public function folder_scope_restricts_to_subtree(): void
    {
        $scope = $this->createFolderAt(null, 'Scope');
        $nested = $this->createFolderAt($scope->id, 'Nested');
        $inside = $this->createFileAt($nested->id, 'dup');
        $outside = $this->createFolderAt(null, 'Outside');
        $this->createFileAt($outside->id, 'dup');

        $page = $this->decode($this->request('GET', '/api/search?name=dup&scope=folder&folderId='.$scope->id->toRfc4122()));

        self::assertSame(1, $page['total']);
        self::assertSame($inside->id->toRfc4122(), $page['items'][0]['id']);
        self::assertSame('dup', $page['items'][0]['name']);
    }

    #[Test]
    #[TestDox('Searching with the root as scope behaves like the all scope')]
    public function root_scope_behaves_like_all(): void
    {
        $folder = $this->createFolderAt(null, 'A');
        $this->createFileAt($folder->id, 'dup');
        $this->createFileAt($this->rootId(), 'dup');

        $rootPage = $this->decode($this->request('GET', '/api/search?name=dup&scope=folder&folderId='.$this->rootId()->toRfc4122()));
        $allPage = $this->decode($this->request('GET', '/api/search?name=dup&scope=all'));

        self::assertSame(2, $rootPage['total']);
        self::assertSame($allPage['total'], $rootPage['total']);
        self::assertSame(array_column($allPage['items'], 'id'), array_column($rootPage['items'], 'id'));
    }

    #[Test]
    #[TestDox('The all scope ignores a folderId, even a bogus one')]
    public function all_scope_ignores_folder_id(): void
    {
        $folder = $this->createFolderAt(null, 'A');
        $file = $this->createFileAt($folder->id, 'dup');

        $page = $this->decode($this->request('GET', '/api/search?name=dup&scope=all&folderId=01890a5d-ac96-774b-bcce-b302099a8057'));

        self::assertSame(1, $page['total']);
        self::assertSame($file->id->toRfc4122(), $page['items'][0]['id']);
    }

    #[Test]
    #[TestDox('Blank or missing search names are 400 validation failures')]
    public function blank_or_missing_name_returns_400(): void
    {
        $error = $this->errorEnvelope($this->request('GET', '/api/search?name=%20&scope=all'), 400, 'validation_failed');
        self::assertSame('The search term must not be blank.', $this->detailFor($error, 'name'));

        $error = $this->errorEnvelope($this->request('GET', '/api/search'), 400, 'validation_failed');
        self::assertNotSame('', $this->detailFor($error, 'name'));
    }

    #[Test]
    #[TestDox('A folder scope without folderId is a 400 with a folderId detail')]
    public function folder_scope_without_folder_id_returns_400(): void
    {
        $error = $this->errorEnvelope($this->request('GET', '/api/search?name=x&scope=folder'), 400, 'validation_failed');

        self::assertSame('A folder scope requires a folderId.', $this->detailFor($error, 'folderId'));
    }

    #[Test]
    #[TestDox('A nonexistent folder scope is a 404')]
    public function nonexistent_folder_scope_returns_404(): void
    {
        $this->errorEnvelope(
            $this->request('GET', '/api/search?name=x&scope=folder&folderId=01890a5d-ac96-774b-bcce-b302099a8057'),
            404,
            'not_found',
        );
    }

    #[Test]
    #[TestDox('A file used as a folder scope is a 404')]
    public function file_folder_scope_returns_404(): void
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
    public function unknown_scope_returns_400(): void
    {
        $error = $this->errorEnvelope($this->request('GET', '/api/search?name=x&scope=everywhere'), 400, 'validation_failed');

        self::assertNotSame('', $this->detailFor($error, 'scope'));
    }

    #[Test]
    #[TestDox('Zero matches are an empty page, never an error')]
    public function zero_matches_return_empty_page(): void
    {
        $page = $this->decode($this->request('GET', '/api/search?name=missing&scope=all'));

        self::assertSame([], $page['items']);
        self::assertSame(0, $page['total']);
    }

    #[Test]
    #[TestDox('Search pages respect limit and offset with a full total')]
    public function search_paginates_with_full_total(): void
    {
        $folder = $this->createFolderAt(null, 'A');
        $this->createFileAt($folder->id, 'dup');
        $this->createFileAt($this->rootId(), 'dup');

        $pageOne = $this->decode($this->request('GET', '/api/search?name=dup&scope=all&limit=1'));
        $pastEnd = $this->decode($this->request('GET', '/api/search?name=dup&scope=all&limit=1&offset=5'));

        self::assertSame(2, $pageOne['total']);
        self::assertCount(1, $pageOne['items']);
        self::assertSame([], $pastEnd['items']);
        self::assertSame(2, $pastEnd['total']);
    }

    #[Test]
    #[TestDox('Search runs a constant number of queries in both scopes')]
    public function search_uses_constant_queries(): void
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
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function sortedIds(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}
