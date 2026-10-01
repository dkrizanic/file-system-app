<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

final class CreateItemsEndpointTest extends ApiTestCase
{
    private const DUPLICATE_MESSAGE = 'An item with this name already exists in the parent folder.';

    #[Test]
    #[TestDox('Creating a folder at the root returns 201 with the trimmed summary and a Location header')]
    public function create_folder_at_root_returns_201_with_summary_and_location(): void
    {
        $response = $this->request('POST', '/api/folders', ['name' => ' Projects ']);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));

        $summary = $this->decode($response);
        self::assertSame('Projects', $summary['name']);
        self::assertSame('folder', $summary['type']);
        self::assertSame($this->rootId()->toRfc4122(), $summary['parentId']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $summary['id']);

        $location = $response->headers->get('Location');
        self::assertSame('/api/items/'.$summary['id'], $location);

        $this->entityManager()->clear();
        $this->queryCounter()->reset();
        $detail = $this->request('GET', $location);
        self::assertSame(200, $detail->getStatusCode());
        self::assertSame($summary['id'], $this->decode($detail)['id']);
        self::assertSame(2, count($this->queryCounter()));
    }

    #[Test]
    #[TestDox('Creating a folder inside a folder stores that folder as parentId')]
    public function create_nested_folder_stores_parent(): void
    {
        $parent = $this->createFolderAt(null, 'Work');

        $response = $this->request('POST', '/api/folders', ['parentId' => $parent->id->toRfc4122(), 'name' => 'Invoices']);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame($parent->id->toRfc4122(), $this->decode($response)['parentId']);
    }

    #[Test]
    #[TestDox('Creating a file returns 201 with a file summary and a Location header')]
    public function create_file_returns_201_with_file_summary(): void
    {
        $folder = $this->createFolderAt(null, 'Work');

        $response = $this->request('POST', '/api/files', ['parentId' => $folder->id->toRfc4122(), 'name' => 'notes.txt']);

        self::assertSame(201, $response->getStatusCode());
        $summary = $this->decode($response);
        self::assertSame('file', $summary['type']);
        self::assertSame('notes.txt', $summary['name']);
        self::assertSame($folder->id->toRfc4122(), $summary['parentId']);
        self::assertSame('/api/items/'.$summary['id'], $response->headers->get('Location'));
    }

    #[Test]
    #[TestDox('A duplicate sibling folder name is a 409 conflict with a name detail, across types')]
    public function duplicate_sibling_name_returns_409_across_types(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $this->createFileAt($folder->id, 'notes.txt');

        $response = $this->request('POST', '/api/folders', ['parentId' => $folder->id->toRfc4122(), 'name' => 'NOTES.TXT']);

        $error = $this->errorEnvelope($response, 409, 'conflict');
        self::assertSame(self::DUPLICATE_MESSAGE, $this->detailFor($error, 'name'));
        $this->assertDetailFields($error, 'name');
    }

    #[Test]
    #[TestDox('A duplicate file name against a sibling folder is a 409 conflict')]
    public function duplicate_file_name_against_folder_returns_409(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $this->createFolderAt($folder->id, 'archive');

        $response = $this->request('POST', '/api/files', ['parentId' => $folder->id->toRfc4122(), 'name' => 'Archive']);

        $error = $this->errorEnvelope($response, 409, 'conflict');
        self::assertSame(self::DUPLICATE_MESSAGE, $this->detailFor($error, 'name'));
    }

    #[Test]
    #[TestDox('Invalid names are rejected with a 400 envelope carrying a name detail')]
    public function invalid_names_return_400_with_name_detail(): void
    {
        $invalid = ['', '   ', str_repeat('a', 256), 'a/b', 'a\\b', '.', '..', "a\u{0007}b"];

        foreach ($invalid as $name) {
            $response = $this->request('POST', '/api/folders', ['name' => $name]);

            $error = $this->errorEnvelope($response, 400, 'validation_failed');
            self::assertNotSame('', $this->detailFor($error, 'name'), sprintf('Expected a name detail for "%s".', $name));
        }
    }

    #[Test]
    #[TestDox('Creating inside a nonexistent parent is a 404')]
    public function create_in_nonexistent_parent_returns_404(): void
    {
        $response = $this->request('POST', '/api/folders', ['parentId' => '01890a5d-ac96-774b-bcce-b302099a8057', 'name' => 'Orphan']);

        $error = $this->errorEnvelope($response, 404, 'not_found');
        self::assertArrayNotHasKey('details', $error);
    }

    #[Test]
    #[TestDox('Creating inside a file parent is a 400 with a parentId detail')]
    public function create_in_file_parent_returns_400_with_parent_detail(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $file = $this->createFileAt($folder->id, 'notes.txt');

        $response = $this->request('POST', '/api/files', ['parentId' => $file->id->toRfc4122(), 'name' => 'nested.txt']);

        $error = $this->errorEnvelope($response, 400, 'validation_failed');
        self::assertSame('The parent must be a folder, not a file.', $this->detailFor($error, 'parentId'));
    }

    #[Test]
    #[TestDox('A missing parentId on a file is a 400 with a parentId detail')]
    public function create_file_without_parent_returns_400(): void
    {
        $response = $this->request('POST', '/api/files', ['name' => 'notes.txt']);

        $error = $this->errorEnvelope($response, 400, 'validation_failed');
        self::assertSame('The parent folder is required.', $this->detailFor($error, 'parentId'));
    }

    #[Test]
    #[TestDox('A malformed parentId uuid is a 400 with a parentId detail')]
    public function malformed_parent_uuid_returns_400(): void
    {
        $response = $this->request('POST', '/api/files', ['parentId' => 'not-a-uuid', 'name' => 'notes.txt']);

        $error = $this->errorEnvelope($response, 400, 'validation_failed');
        self::assertNotSame('', $this->detailFor($error, 'parentId'));
    }

    #[Test]
    #[TestDox('Malformed JSON is a 400 envelope without leaking the parse error')]
    public function malformed_json_returns_400_envelope(): void
    {
        $response = $this->requestRaw('POST', '/api/folders', '{"name": ');

        $error = $this->errorEnvelope($response, 400, 'validation_failed');
        self::assertSame('The request body is invalid.', $error['message']);
        self::assertSame([], $error['details']);
    }

    #[Test]
    #[TestDox('Unknown JSON fields are ignored')]
    public function unknown_fields_are_ignored(): void
    {
        $response = $this->request('POST', '/api/folders', ['name' => 'Docs', 'bogus' => 'ignored']);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Docs', $this->decode($response)['name']);
    }

    #[Test]
    #[TestDox('Creating a folder costs a constant number of queries')]
    public function create_folder_uses_constant_queries(): void
    {
        $this->createFolderAt(null, 'Work');
        $this->entityManager()->clear();

        $this->queryCounter()->reset();
        $response = $this->request('POST', '/api/folders', ['name' => 'Second']);

        self::assertSame(201, $response->getStatusCode());
        // parent find + the savepoint pair the nested test transaction adds
        // around the insert
        self::assertSame(4, count($this->queryCounter()));
    }
}
