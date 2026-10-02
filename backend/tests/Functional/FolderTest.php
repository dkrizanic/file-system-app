<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

final class FolderTest extends ApiTestCase
{
    #[Test]
    #[TestDox('Creating a folder at the root returns 201 with the trimmed summary and a Location header')]
    public function createFolderAtRootReturns201WithSummaryAndLocation(): void
    {
        $response = $this->request('POST', '/api/folders', ['name' => ' Projects ']);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));

        $summary = $this->decode($response);
        self::assertSame('Projects', $summary['name']);
        self::assertSame('folder', $summary['type']);
        self::assertSame($this->rootId()->toRfc4122(), $summary['parentId']);

        $location = '/api/items/'.$this->stringValue($summary, 'id');
        self::assertSame($location, $response->headers->get('Location'));

        $detail = $this->request('GET', $location);
        self::assertSame(200, $detail->getStatusCode());
        self::assertSame($summary['id'], $this->decode($detail)['id']);
    }

    #[Test]
    #[TestDox('Creating a sibling with an existing name is a 409 conflict on name')]
    public function duplicateSiblingNameReturns409(): void
    {
        $this->createFolderAt(null, 'Work');

        $response = $this->request('POST', '/api/folders', ['name' => 'WORK']);

        $error = $this->errorEnvelope($response, 409, 'conflict');
        self::assertSame('An item with this name already exists in the parent folder.', $this->detailFor($error, 'name'));
    }

    #[Test]
    #[TestDox('Listing a folder returns folders first with page metadata')]
    public function listingReturnsFoldersFirstWithMetadata(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $this->createFileAt($folder->id, 'notes.txt');
        $this->createFolderAt($folder->id, 'Invoices');

        $response = $this->request('GET', '/api/folders/'.$folder->id->toRfc4122().'/items?limit=50&offset=0');

        self::assertSame(200, $response->getStatusCode());

        $page = $this->decode($response);
        self::assertSame(['items', 'total', 'limit', 'offset'], array_keys($page));
        self::assertSame(2, $page['total']);
        self::assertSame(50, $page['limit']);
        self::assertSame(0, $page['offset']);
        self::assertSame('Invoices', $this->pageItems($page)[0]['name']);
        self::assertSame('folder', $this->pageItems($page)[0]['type']);
        self::assertSame('notes.txt', $this->pageItems($page)[1]['name']);
    }

    #[Test]
    #[TestDox('Listing an unknown folder is a 404 envelope')]
    public function listingUnknownFolderReturns404(): void
    {
        $response = $this->request('GET', '/api/folders/00000000-0000-7000-8000-000000000000/items');

        $this->errorEnvelope($response, 404, 'not_found');
    }
}
