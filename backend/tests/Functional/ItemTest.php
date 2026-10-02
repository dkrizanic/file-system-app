<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

final class ItemTest extends ApiTestCase
{
    #[Test]
    #[TestDox('A file detail carries its root-first parent path without the item itself')]
    public function fileDetailCarriesParentPath(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $file = $this->createFileAt($folder->id, '2026.pdf');

        $response = $this->request('GET', $this->itemUri($file->id));

        self::assertSame(200, $response->getStatusCode());

        $detail = $this->decode($response);
        self::assertSame(['id', 'type', 'name', 'parentId', 'parentPath'], array_keys($detail));
        self::assertSame('2026.pdf', $detail['name']);
        self::assertSame($folder->id->toRfc4122(), $detail['parentId']);
        self::assertSame(
            [
                ['id' => $this->rootId()->toRfc4122(), 'name' => 'Root'],
                ['id' => $folder->id->toRfc4122(), 'name' => 'Work'],
            ],
            $detail['parentPath'],
        );
    }

    #[Test]
    #[TestDox('An unknown item id is a 404 envelope without details')]
    public function unknownItemReturns404(): void
    {
        $response = $this->request('GET', $this->itemUri($this->unknownId()));

        $error = $this->errorEnvelope($response, 404, 'not_found');
        self::assertArrayNotHasKey('details', $error);
    }

    #[Test]
    #[TestDox('Renaming an item returns the renamed summary')]
    public function renameReturnsRenamedSummary(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $file = $this->createFileAt($folder->id, 'notes.txt');

        $response = $this->request('PATCH', $this->itemUri($file->id), ['name' => 'Notes 2026.txt']);

        self::assertSame(200, $response->getStatusCode());

        $summary = $this->decode($response);
        self::assertSame('Notes 2026.txt', $summary['name']);
        self::assertSame($file->id->toRfc4122(), $summary['id']);
    }

    #[Test]
    #[TestDox('Renaming onto a sibling name is a 409 conflict on name')]
    public function renameToSiblingNameReturns409(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $this->createFileAt($folder->id, 'existing.txt');
        $file = $this->createFileAt($folder->id, 'notes.txt');

        $response = $this->request('PATCH', $this->itemUri($file->id), ['name' => 'EXISTING.TXT']);

        $error = $this->errorEnvelope($response, 409, 'conflict');
        self::assertSame('An item with this name already exists in the parent folder.', $this->detailFor($error, 'name'));
    }

    #[Test]
    #[TestDox('Deleting a folder removes the whole subtree and keeps the root')]
    public function deletingFolderCascadesSubtree(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $nested = $this->createFolderAt($folder->id, 'Invoices');
        $file = $this->createFileAt($nested->id, '2026.pdf');

        $response = $this->request('DELETE', $this->itemUri($folder->id));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());

        foreach ([$folder->id, $nested->id, $file->id] as $goneId) {
            $detail = $this->request('GET', $this->itemUri($goneId));
            self::assertSame(404, $detail->getStatusCode(), $goneId->toRfc4122());
        }

        $root = $this->request('GET', $this->itemUri($this->rootId()));
        self::assertSame(200, $root->getStatusCode());
    }

    #[Test]
    #[TestDox('Deleting an unknown item is a 404 envelope')]
    public function deletingUnknownItemReturns404(): void
    {
        $response = $this->request('DELETE', $this->itemUri($this->unknownId()));

        $this->errorEnvelope($response, 404, 'not_found');
    }
}
