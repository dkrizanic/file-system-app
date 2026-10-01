<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contract\ItemServiceInterface;
use App\DTO\Write\RenameItem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/items/{id}')]
final class ItemController extends AbstractController
{
    public function __construct(
        private readonly ItemServiceInterface $items,
    ) {
    }

    #[Route('', name: 'api_item_show', methods: ['GET'])]
    public function show(Uuid $id): JsonResponse
    {
        return $this->json($this->items->getItem($id));
    }

    #[Route('', name: 'api_item_rename', methods: ['PATCH'])]
    public function rename(Uuid $id, #[MapRequestPayload] RenameItem $command): JsonResponse
    {
        return $this->json($this->items->rename($id, $command));
    }

    #[Route('', name: 'api_item_delete', methods: ['DELETE'])]
    public function delete(Uuid $id): Response
    {
        $this->items->delete($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
