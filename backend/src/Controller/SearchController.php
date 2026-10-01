<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contract\ItemServiceInterface;
use App\DTO\Write\SearchQuery;
use App\DTO\Write\SuggestionQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class SearchController extends AbstractController
{
    public function __construct(
        private readonly ItemServiceInterface $items,
    ) {
    }

    #[Route('/search', name: 'api_search', methods: ['GET'])]
    public function search(#[MapQueryString(mapWhenEmpty: true)] SearchQuery $query): JsonResponse
    {
        return $this->json($this->items->search($query));
    }

    #[Route('/suggestions', name: 'api_suggestions', methods: ['GET'])]
    public function suggestions(#[MapQueryString] SuggestionQuery $query = new SuggestionQuery()): JsonResponse
    {
        return $this->json($this->items->suggestions($query));
    }
}
