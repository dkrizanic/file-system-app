<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Contract\ItemServiceInterface;
use App\DTO\Read\ItemSummary;
use App\DTO\Write\CreateFile;
use App\DTO\Write\CreateFolder;
use App\Entity\Item;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

abstract class ApiTestCase extends FunctionalTestCase
{
    private KernelBrowser $browser;

    protected function boot(): void
    {
        // createClient() owns the boot; disableReboot() keeps this kernel (and
        // its transaction-bound entity manager) alive across requests.
        $this->browser = self::createClient();
        $this->browser->disableReboot();
    }

    protected function client(): KernelBrowser
    {
        return $this->browser;
    }

    protected function items(): ItemServiceInterface
    {
        $service = self::getContainer()->get(ItemServiceInterface::class);
        \assert($service instanceof ItemServiceInterface);

        return $service;
    }

    protected function createFolderAt(?Uuid $parentId, string $name): ItemSummary
    {
        return $this->items()->createFolder(new CreateFolder($parentId, $name));
    }

    protected function createFileAt(Uuid $parentId, string $name): ItemSummary
    {
        return $this->items()->createFile(new CreateFile($parentId, $name));
    }

    protected function rootId(): Uuid
    {
        return Uuid::fromString(Item::ROOT_ID);
    }

    protected function unknownId(): Uuid
    {
        return Uuid::fromString('00000000-0000-7000-8000-000000000000');
    }

    protected function itemUri(Uuid $id): string
    {
        return '/api/items/'.$id->toRfc4122();
    }

    /**
     * @param array<string, mixed>|null $body
     */
    protected function request(string $method, string $uri, ?array $body = null): Response
    {
        return $this->send($method, $uri, null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(Response $response): array
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($response->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    /**
     * @param array<string, mixed> $page
     *
     * @return list<array<string, mixed>>
     */
    protected function pageItems(array $page): array
    {
        $items = $page['items'] ?? null;
        \assert(\is_array($items));

        /** @var list<array<string, mixed>> $values */
        $values = array_values($items);

        return $values;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function stringValue(array $payload, string $key): string
    {
        self::assertArrayHasKey($key, $payload);
        self::assertIsString($payload[$key]);

        return $payload[$key];
    }

    /**
     * @return array{code: string, message: string, details?: list<array{field: string, message: string}>}
     */
    protected function errorEnvelope(Response $response, int $status, string $code): array
    {
        self::assertSame($status, $response->getStatusCode(), $response->getContent() ?: '');
        self::assertSame('application/json', $response->headers->get('Content-Type'));

        $payload = $this->decode($response);
        self::assertArrayHasKey('error', $payload);

        /** @var array{code: string, message: string, details?: list<array{field: string, message: string}>} $error */
        $error = $payload['error'];
        self::assertSame($code, $error['code']);
        self::assertNotSame('', $error['message']);

        return $error;
    }

    /**
     * @param array{code: string, message: string, details?: list<array{field: string, message: string}>} $error
     */
    protected function detailFor(array $error, string $field): string
    {
        foreach ($error['details'] ?? [] as $detail) {
            if ($detail['field'] === $field) {
                return $detail['message'];
            }
        }

        self::fail(sprintf('No error detail for field "%s" in: %s', $field, json_encode($error)));
    }

    protected function send(string $method, string $uri, ?string $content): Response
    {
        $this->client()->request($method, $uri, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $content);

        return $this->client()->getResponse();
    }
}
