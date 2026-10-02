<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class RootFolderTest extends ApiTestCase
{
    #[Test]
    #[TestDox('The root folder endpoint returns the seeded root as a summary')]
    public function rootFolderEndpointReturnsSeededRootSummary(): void
    {
        $response = $this->request('GET', '/api/root-folder');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $summary = $this->decode($response);
        self::assertSame(['id', 'type', 'name', 'parentId'], array_keys($summary));
        self::assertSame($this->rootId()->toRfc4122(), $summary['id']);
        self::assertSame('folder', $summary['type']);
        self::assertSame('Root', $summary['name']);
        self::assertNull($summary['parentId']);
    }
}
