<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

final class FileTest extends ApiTestCase
{
    #[Test]
    #[TestDox('Creating a file returns 201 with the trimmed summary and a Location header')]
    public function createFileReturns201WithSummaryAndLocation(): void
    {
        $folder = $this->createFolderAt(null, 'Work');

        $response = $this->request('POST', '/api/files', [
            'parentId' => $folder->id->toRfc4122(),
            'name' => ' notes.txt ',
        ]);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));

        $summary = $this->decode($response);
        self::assertSame('notes.txt', $summary['name']);
        self::assertSame('file', $summary['type']);
        self::assertSame($folder->id->toRfc4122(), $summary['parentId']);

        $location = '/api/items/'.$this->stringValue($summary, 'id');
        self::assertSame($location, $response->headers->get('Location'));

        $detail = $this->request('GET', $location);
        self::assertSame(200, $detail->getStatusCode());
        self::assertSame($summary['id'], $this->decode($detail)['id']);
    }
}
