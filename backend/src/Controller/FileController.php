<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contract\ItemServiceInterface;
use App\DTO\Write\CreateFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/files')]
final class FileController extends AbstractController
{
    public function __construct(
        private readonly ItemServiceInterface $items,
    ) {
    }

    #[Route('', name: 'api_file_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateFile $command): JsonResponse
    {
        $summary = $this->items->createFile($command);

        return $this->json(
            $summary,
            Response::HTTP_CREATED,
            ['Location' => $this->generateUrl('api_item_show', ['id' => $summary->id->toRfc4122()])],
        );
    }
}
