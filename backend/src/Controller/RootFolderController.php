<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contract\ItemServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/root-folder', name: 'api_root_folder_show', methods: ['GET'])]
final class RootFolderController extends AbstractController
{
    public function __construct(
        private readonly ItemServiceInterface $items,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->items->rootFolder());
    }
}
