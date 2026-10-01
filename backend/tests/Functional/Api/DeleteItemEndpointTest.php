<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Uid\Uuid;

final class DeleteItemEndpointTest extends ApiTestCase
{
    #[Test]
    #[TestDox('Deleting a file returns 204 with an empty body and removes it from the listing')]
    public function deleteFileReturns204AndRemovesFile(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $file = $this->createFileAt($folder->id, 'notes.txt');

        $response = $this->request('DELETE', $this->itemUri($file->id));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());

        $page = $this->decode($this->request('GET', '/api/folders/'.$folder->id->toRfc4122().'/items'));
        self::assertSame(0, $page['total']);
    }

    #[Test]
    #[TestDox('A deleted file name can be recreated in the same folder')]
    public function deletedFileNameCanBeRecreated(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $file = $this->createFileAt($folder->id, 'notes.txt');
        $this->request('DELETE', $this->itemUri($file->id));

        $response = $this->request('POST', '/api/files', ['parentId' => $folder->id->toRfc4122(), 'name' => 'notes.txt']);

        self::assertSame(201, $response->getStatusCode());
    }

    #[Test]
    #[TestDox('Deleting a folder removes its whole subtree')]
    public function deleteFolderRemovesSubtree(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $nested = $this->createFolderAt($folder->id, 'Invoices');
        $file = $this->createFileAt($nested->id, '2026.pdf');

        $response = $this->request('DELETE', $this->itemUri($folder->id));

        self::assertSame(204, $response->getStatusCode());
        $this->errorEnvelope($this->request('GET', $this->itemUri($file->id)), 404, 'not_found');

        $page = $this->decode($this->request('GET', '/api/folders/'.$this->rootId()->toRfc4122().'/items'));
        self::assertSame([], array_column($this->pageItems($page), 'id'));
    }

    #[Test]
    #[TestDox('Deleting an unknown item is a 404')]
    public function deleteUnknownItemReturns404(): void
    {
        $this->errorEnvelope(
            $this->request('DELETE', $this->itemUri(Uuid::fromString('01890a5d-ac96-774b-bcce-b302099a8057'))),
            404,
            'not_found',
        );
    }

    #[Test]
    #[TestDox('Deleting the root is a 400 with an id detail and leaves the tree intact')]
    public function deleteRootReturns400AndKeepsTree(): void
    {
        $this->createFolderAt(null, 'Work');

        $error = $this->errorEnvelope(
            $this->request('DELETE', $this->itemUri($this->rootId())),
            400,
            'validation_failed',
        );

        self::assertSame('The root folder cannot be renamed or deleted.', $this->detailFor($error, 'id'));

        $page = $this->decode($this->request('GET', '/api/folders/'.$this->rootId()->toRfc4122().'/items'));
        self::assertSame(1, $page['total']);
    }

    #[Test]
    #[TestDox('Deleting runs a constant number of queries')]
    public function deleteUsesConstantQueries(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $this->entityManager()->clear();

        $this->queryCounter()->reset();
        $response = $this->request('DELETE', $this->itemUri($folder->id));

        self::assertSame(204, $response->getStatusCode());
        // item find + the savepoint pair the nested test transaction adds
        // around the delete
        self::assertSame(4, count($this->queryCounter()));
    }
}
