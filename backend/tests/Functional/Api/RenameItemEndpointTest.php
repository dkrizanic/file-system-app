<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Uid\Uuid;

final class RenameItemEndpointTest extends ApiTestCase
{
    #[Test]
    #[TestDox('A case-only rename succeeds and updates the stored casing')]
    public function case_only_rename_updates_casing(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $file = $this->createFileAt($folder->id, 'notes');

        $response = $this->request('PATCH', $this->itemUri($file->id), ['name' => 'Notes']);

        self::assertSame(200, $response->getStatusCode());
        $summary = $this->decode($response);
        self::assertSame('Notes', $summary['name']);
        self::assertSame('file', $summary['type']);
        self::assertSame($folder->id->toRfc4122(), $summary['parentId']);
    }

    #[Test]
    #[TestDox('Renaming to the current name is a no-op success')]
    public function rename_to_own_name_succeeds(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $file = $this->createFileAt($folder->id, 'notes');

        $response = $this->request('PATCH', $this->itemUri($file->id), ['name' => 'notes']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('notes', $this->decode($response)['name']);
    }

    #[Test]
    #[TestDox('Renaming onto a sibling name is a 409 with a name detail')]
    public function rename_onto_sibling_name_returns_409(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $file = $this->createFileAt($folder->id, 'notes');
        $this->createFileAt($folder->id, 'todo');

        $response = $this->request('PATCH', $this->itemUri($file->id), ['name' => 'TODO']);

        $error = $this->errorEnvelope($response, 409, 'conflict');
        self::assertNotSame('', $this->detailFor($error, 'name'));
    }

    #[Test]
    #[TestDox('Renaming an unknown item is a 404')]
    public function rename_unknown_item_returns_404(): void
    {
        $this->errorEnvelope(
            $this->request('PATCH', $this->itemUri(Uuid::fromString('01890a5d-ac96-774b-bcce-b302099a8057')), ['name' => 'X']),
            404,
            'not_found',
        );
    }

    #[Test]
    #[TestDox('Renaming the root is a 400 with an id detail')]
    public function rename_root_returns_400(): void
    {
        $error = $this->errorEnvelope(
            $this->request('PATCH', $this->itemUri($this->rootId()), ['name' => 'Home']),
            400,
            'validation_failed',
        );

        self::assertSame('The root folder cannot be renamed or deleted.', $this->detailFor($error, 'id'));
    }

    #[Test]
    #[TestDox('An invalid rename name is a 400 with a name detail')]
    public function invalid_rename_name_returns_400(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $file = $this->createFileAt($folder->id, 'notes');

        $error = $this->errorEnvelope(
            $this->request('PATCH', $this->itemUri($file->id), ['name' => '  ']),
            400,
            'validation_failed',
        );

        self::assertSame('Name must not be blank.', $this->detailFor($error, 'name'));
    }

    #[Test]
    #[TestDox('Search and suggestions reflect the new name after a rename')]
    public function search_reflects_renamed_name(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $file = $this->createFileAt($folder->id, 'notes');
        $this->request('PATCH', $this->itemUri($file->id), ['name' => 'Agenda']);

        $page = $this->decode($this->request('GET', '/api/search?name=AGENDA'));

        self::assertSame([$file->id->toRfc4122()], array_column($page['items'], 'id'));
        self::assertSame('Agenda', $page['items'][0]['name']);

        $old = $this->decode($this->request('GET', '/api/search?name=notes'));
        self::assertSame(0, $old['total']);
    }

    #[Test]
    #[TestDox('A malformed rename body is a 400 envelope')]
    public function malformed_rename_body_returns_400(): void
    {
        $folder = $this->createFolderAt(null, 'Work');
        $file = $this->createFileAt($folder->id, 'notes');

        $error = $this->errorEnvelope(
            $this->requestRaw('PATCH', $this->itemUri($file->id), 'no json'),
            400,
            'validation_failed',
        );

        self::assertSame('The request body is invalid.', $error['message']);
    }
}
