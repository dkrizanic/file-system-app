<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Uid\Uuid;

final class ItemDetailEndpointTest extends ApiTestCase
{
    #[Test]
    #[TestDox('A file detail carries its root-first parent path without the item itself')]
    public function file_detail_carries_parent_path(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $nested = $this->createFolderAt($folder->id, 'Invoices');
        $file = $this->createFileAt($nested->id, '2026.pdf');

        $response = $this->request('GET', $this->itemUri($file->id));

        self::assertSame(200, $response->getStatusCode());

        $detail = $this->decode($response);
        self::assertSame(['id', 'type', 'name', 'parentId', 'parentPath'], array_keys($detail));
        self::assertSame('file', $detail['type']);
        self::assertSame('2026.pdf', $detail['name']);
        self::assertSame($nested->id->toRfc4122(), $detail['parentId']);
        self::assertSame(
            [
                ['id' => $this->rootId()->toRfc4122(), 'name' => 'Root'],
                ['id' => $folder->id->toRfc4122(), 'name' => 'Work'],
                ['id' => $nested->id->toRfc4122(), 'name' => 'Invoices'],
            ],
            $detail['parentPath'],
        );
    }

    #[Test]
    #[TestDox('A root-level folder has only the root in its path')]
    public function root_level_folder_path_is_only_root(): void
    {
        $folder = $this->createFolderAt(null, 'Work');

        $detail = $this->decode($this->request('GET', $this->itemUri($folder->id)));

        self::assertSame('folder', $detail['type']);
        self::assertSame(
            [['id' => $this->rootId()->toRfc4122(), 'name' => 'Root']],
            $detail['parentPath'],
        );
    }

    #[Test]
    #[TestDox('The root itself has an empty path')]
    public function root_detail_has_empty_path(): void
    {
        $detail = $this->decode($this->request('GET', $this->itemUri($this->rootId())));

        self::assertSame('folder', $detail['type']);
        self::assertSame('Root', $detail['name']);
        self::assertNull($detail['parentId']);
        self::assertSame([], $detail['parentPath']);
    }

    #[Test]
    #[TestDox('An unknown item id is a 404 envelope without details')]
    public function unknown_item_returns_404(): void
    {
        $error = $this->errorEnvelope(
            $this->request('GET', $this->itemUri(Uuid::fromString('01890a5d-ac96-774b-bcce-b302099a8057'))),
            404,
            'not_found',
        );

        self::assertArrayNotHasKey('details', $error);
    }

    #[Test]
    #[TestDox('Fetching a detail runs a constant number of queries')]
    public function detail_uses_constant_queries(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $file = $this->createFileAt($folder->id, 'notes.txt');
        $this->entityManager()->clear();

        $this->queryCounter()->reset();
        $response = $this->request('GET', $this->itemUri($file->id));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, count($this->queryCounter()));
    }
}
