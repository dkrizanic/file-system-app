<?php

declare(strict_types=1);

namespace App\Mapper;

use App\DTO\Read\ItemDetail;
use App\DTO\Read\ItemSummary;
use App\DTO\Read\Page;
use App\DTO\Read\PathEntry;
use App\DTO\Read\Suggestion;
use App\DTO\Read\SuggestionList;
use App\DTO\Write\CreateFile;
use App\DTO\Write\CreateFolder;
use App\DTO\Write\RenameItem;
use App\Entity\Item;
use App\Entity\ItemType;
use App\Service\NameNormalizer;
use Symfony\Component\Uid\Uuid;

final class ItemMapper
{
    public function __construct(
        private readonly NameNormalizer $normalizer,
    ) {
    }

    public function toFolder(CreateFolder $command, Item $parent): Item
    {
        return $this->toItem($command->name, ItemType::Folder, $parent);
    }

    public function toFile(CreateFile $command, Item $parent): Item
    {
        return $this->toItem($command->name, ItemType::File, $parent);
    }

    public function applyRename(Item $item, RenameItem $command): void
    {
        $item->rename(trim($command->name), $this->normalizer);
    }

    public function toSummary(Item $item): ItemSummary
    {
        return new ItemSummary(
            $item->getId(),
            $item->getType(),
            $item->getName(),
            $item->getParentId(),
        );
    }

    /**
     * @param list<array{id: Uuid, name: string}> $ancestorPath
     */
    public function toDetail(Item $item, array $ancestorPath): ItemDetail
    {
        return new ItemDetail(
            $item->getId(),
            $item->getType(),
            $item->getName(),
            $item->getParentId(),
            $this->toPathEntries($ancestorPath),
        );
    }

    /**
     * @param array{id: Uuid, name: string, parentId: ?Uuid, path: list<array{id: Uuid, name: string}>} $match
     */
    public function toMatchDetail(array $match): ItemDetail
    {
        return new ItemDetail(
            $match['id'],
            ItemType::File,
            $match['name'],
            $match['parentId'],
            $this->toPathEntries($match['path']),
        );
    }

    /**
     * @param array{items: list<Item>, total: int} $children
     *
     * @return Page<ItemSummary>
     */
    public function toSummaryPage(array $children, int $limit, int $offset): Page
    {
        return new Page(
            array_map($this->toSummary(...), $children['items']),
            $children['total'],
            $limit,
            $offset,
        );
    }

    /**
     * @param array{items: list<array{id: Uuid, name: string, parentId: ?Uuid, path: list<array{id: Uuid, name: string}>}>, total: int} $matches
     *
     * @return Page<ItemDetail>
     */
    public function toDetailPage(array $matches, int $limit, int $offset): Page
    {
        return new Page(
            array_map($this->toMatchDetail(...), $matches['items']),
            $matches['total'],
            $limit,
            $offset,
        );
    }

    /**
     * @param list<array{id: Uuid, name: string, parentId: ?Uuid, path: list<array{id: Uuid, name: string}>}> $matches
     */
    public function toSuggestionList(array $matches): SuggestionList
    {
        return new SuggestionList(array_map(
            fn (array $match): Suggestion => new Suggestion(
                $match['id'],
                $match['name'],
                $this->toPathEntries($match['path']),
            ),
            $matches,
        ));
    }

    private function toItem(string $name, ItemType $type, Item $parent): Item
    {
        return new Item(trim($name), $type, $parent, $this->normalizer);
    }

    /**
     * @param list<array{id: Uuid, name: string}> $path
     *
     * @return list<PathEntry>
     */
    private function toPathEntries(array $path): array
    {
        return array_map(
            static fn (array $entry): PathEntry => new PathEntry($entry['id'], $entry['name']),
            $path,
        );
    }
}
