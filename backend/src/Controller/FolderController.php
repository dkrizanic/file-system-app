<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contract\ItemServiceInterface;
use App\DTO\Write\CreateFolder;
use App\DTO\Write\PaginationQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/folders')]
final class FolderController extends AbstractController
{
    public function __construct(
        private readonly ItemServiceInterface $items,
    ) {
    }

    #[Route('', name: 'api_folder_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateFolder $command): JsonResponse
    {
        $summary = $this->items->createFolder($command);

        return $this->json(
            $summary,
            Response::HTTP_CREATED,
            ['Location' => $this->generateUrl('api_item_show', ['id' => $summary->id->toRfc4122()])],
        );
    }

    #[Route('/{id}/items', name: 'api_folder_items', methods: ['GET'])]
    public function items(Uuid $id, #[MapQueryString] PaginationQuery $query = new PaginationQuery()): JsonResponse
    {
        return $this->json($this->items->listChildren($id, $query));
    }
}
