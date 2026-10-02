<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

final class FlowTest extends ApiTestCase
{
    #[Test]
    #[TestDox('The main user flow works end to end: nest, rename, search, suggest, cascade-delete')]
    public function mainFlowWorksEndToEnd(): void
    {
        $work = $this->decode($this->request('POST', '/api/folders', ['name' => 'Work']));
        $invoices = $this->decode($this->request('POST', '/api/folders', [
            'parentId' => $this->stringValue($work, 'id'),
            'name' => 'Invoices',
        ]));
        $file = $this->decode($this->request('POST', '/api/files', [
            'parentId' => $this->stringValue($invoices, 'id'),
            'name' => '2026.pdf',
        ]));
        $fileId = $this->stringValue($file, 'id');

        $renamed = $this->decode($this->request('PATCH', '/api/items/'.$fileId, ['name' => 'Report 2026.pdf']));
        self::assertSame('Report 2026.pdf', $renamed['name']);

        $this->entityManager()->clear();
        $this->queryCounter()->reset();
        $search = $this->decode($this->request('GET', '/api/search?name=report%202026.PDF&scope=all'));
        self::assertSame(1, $search['total']);
        self::assertSame(
            [
                ['id' => $this->rootId()->toRfc4122(), 'name' => 'Root'],
                ['id' => $work['id'], 'name' => 'Work'],
                ['id' => $invoices['id'], 'name' => 'Invoices'],
            ],
            $this->pageItems($search)[0]['parentPath'],
        );
        // The match and its whole folder path come from one recursive CTE.
        // More queries here mean a per-node path lookup crept back in (N+1).
        self::assertSame(1, count($this->queryCounter()));

        $suggestions = $this->pageItems($this->decode($this->request('GET', '/api/suggestions?prefix=report')));
        self::assertSame('Report 2026.pdf', $suggestions[0]['name']);

        $deleted = $this->request('DELETE', '/api/items/'.$this->stringValue($work, 'id'));
        self::assertSame(204, $deleted->getStatusCode());

        self::assertSame(404, $this->request('GET', '/api/items/'.$fileId)->getStatusCode());

        $rootPage = $this->decode($this->request('GET', '/api/folders/'.$this->rootId()->toRfc4122().'/items'));
        self::assertEmpty($this->pageItems($rootPage));
        self::assertSame(0, $rootPage['total']);
    }
}
