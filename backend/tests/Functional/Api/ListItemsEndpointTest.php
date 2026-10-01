<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

final class ListItemsEndpointTest extends ApiTestCase
{
    #[Test]
    #[TestDox('Listing a folder returns folders first, then files, with page metadata')]
    public function list_returns_folders_first_then_files_with_metadata(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $this->createFileAt($folder->id, 'zebra');
        $this->createFolderAt($folder->id, 'Zeta');
        $this->createFileAt($folder->id, 'Apple');
        $this->createFolderAt($folder->id, 'alpha');

        $response = $this->request('GET', '/api/folders/'.$folder->id->toRfc4122().'/items');

        self::assertSame(200, $response->getStatusCode());

        $page = $this->decode($response);
        self::assertSame(
            ['folder alpha', 'folder Zeta', 'file Apple', 'file zebra'],
            array_map(static fn (array $item): string => $item['type'].' '.$item['name'], $page['items']),
        );
        self::assertSame(4, $page['total']);
        self::assertSame(50, $page['limit']);
        self::assertSame(0, $page['offset']);

        foreach ($page['items'] as $item) {
            self::assertSame(['id', 'type', 'name', 'parentId'], array_keys($item));
            self::assertSame($folder->id->toRfc4122(), $item['parentId']);
        }
    }

    #[Test]
    #[TestDox('Listing respects limit and offset and keeps the order stable across pages')]
    public function list_paginates_stably(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $names = [];
        foreach (range(1, 5) as $i) {
            $names[] = sprintf('f%d', $i);
            $this->createFileAt($folder->id, sprintf('f%d', $i));
        }

        $first = $this->decode($this->request('GET', '/api/folders/'.$folder->id->toRfc4122().'/items?limit=2&offset=0'));
        $second = $this->decode($this->request('GET', '/api/folders/'.$folder->id->toRfc4122().'/items?limit=2&offset=2'));
        $third = $this->decode($this->request('GET', '/api/folders/'.$folder->id->toRfc4122().'/items?limit=2&offset=4'));

        self::assertSame(['f1', 'f2'], array_column($first['items'], 'name'));
        self::assertSame(['f3', 'f4'], array_column($second['items'], 'name'));
        self::assertSame(['f5'], array_column($third['items'], 'name'));
        self::assertSame(5, $first['total']);
        self::assertSame(2, $first['limit']);
        self::assertSame(2, $second['offset']);
    }

    #[Test]
    #[TestDox('An offset past the end returns empty items with the true total')]
    public function offset_past_end_returns_empty_items_with_total(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $this->createFileAt($folder->id, 'only.txt');

        $page = $this->decode($this->request('GET', '/api/folders/'.$folder->id->toRfc4122().'/items?offset=99'));

        self::assertSame([], $page['items']);
        self::assertSame(1, $page['total']);
    }

    #[Test]
    #[TestDox('Listing the root shows top-level items')]
    public function listing_root_shows_top_level_items(): void
    {
        $this->createFolderAt(null, 'Top');

        $page = $this->decode($this->request('GET', '/api/folders/'.$this->rootId()->toRfc4122().'/items'));

        self::assertSame(1, $page['total']);
        self::assertSame('Top', $page['items'][0]['name']);
    }

    #[Test]
    #[TestDox('An empty folder lists an empty page with a zero total')]
    public function empty_folder_lists_empty_page(): void
    {
        $folder = $this->createFolderAt(null, 'Empty');

        $page = $this->decode($this->request('GET', '/api/folders/'.$folder->id->toRfc4122().'/items'));

        self::assertSame([], $page['items']);
        self::assertSame(0, $page['total']);
    }

    #[Test]
    #[TestDox('An unknown folder id is a 404 envelope')]
    public function unknown_folder_returns_404(): void
    {
        $error = $this->errorEnvelope(
            $this->request('GET', '/api/folders/01890a5d-ac96-774b-bcce-b302099a8057/items'),
            404,
            'not_found',
        );

        self::assertArrayNotHasKey('details', $error);
    }

    #[Test]
    #[TestDox('A file id addressed as a folder is a 404')]
    public function file_addressed_as_folder_returns_404(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $file = $this->createFileAt($folder->id, 'notes.txt');

        $this->errorEnvelope(
            $this->request('GET', '/api/folders/'.$file->id->toRfc4122().'/items'),
            404,
            'not_found',
        );
    }

    #[Test]
    #[TestDox('A malformed folder id path is a 404')]
    public function malformed_folder_id_returns_404(): void
    {
        $response = $this->request('GET', '/api/folders/not-a-uuid/items');

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    #[TestDox('Pagination inputs outside their range are 400 validation failures')]
    public function invalid_pagination_returns_400(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $uri = '/api/folders/'.$folder->id->toRfc4122().'/items';

        foreach (['limit=101', 'limit=0', 'limit=abc', 'offset=-1'] as $query) {
            $error = $this->errorEnvelope($this->request('GET', $uri.'?'.$query), 400, 'validation_failed');
            self::assertNotSame('', $error['message'], sprintf('Expected a message for "%s".', $query));
        }
    }

    #[Test]
    #[TestDox('Listing runs a constant number of queries')]
    public function listing_uses_constant_queries(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $this->createFileAt($folder->id, 'notes.txt');
        $this->createFolderAt($folder->id, 'Sub');
        $this->entityManager()->clear();

        $this->queryCounter()->reset();
        $response = $this->request('GET', '/api/folders/'.$folder->id->toRfc4122().'/items');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(3, count($this->queryCounter()));
    }
}
