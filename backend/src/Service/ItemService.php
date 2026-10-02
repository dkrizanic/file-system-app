<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\ItemRepositoryInterface;
use App\Contract\ItemServiceInterface;
use App\DTO\Read\ItemDetail;
use App\DTO\Read\ItemSummary;
use App\DTO\Read\Page;
use App\DTO\Read\SuggestionList;
use App\DTO\Write\CreateFile;
use App\DTO\Write\CreateFolder;
use App\DTO\Write\PaginationQuery;
use App\DTO\Write\RenameItem;
use App\DTO\Write\SearchQuery;
use App\DTO\Write\SearchScope;
use App\DTO\Write\SuggestionQuery;
use App\Entity\Item;
use App\Entity\ItemType;
use App\Exception\DuplicateItemNameException;
use App\Exception\InvalidParentTypeException;
use App\Exception\ItemNotFoundException;
use App\Exception\ParentNotFoundException;
use App\Exception\RootChangeForbiddenException;
use App\Mapper\ItemMapper;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class ItemService implements ItemServiceInterface
{
    private const SUGGESTION_LIMIT = 10;

    public function __construct(
        private readonly ItemRepositoryInterface $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ItemMapper $mapper,
    ) {
    }

    public function createFolder(CreateFolder $command): ItemSummary
    {
        $parent = null === $command->parentId
            ? $this->root()
            : $this->folderParent($command->parentId);
        $item = $this->mapper->toFolder($command, $parent);

        $this->flushCreating($item);

        return $this->mapper->toSummary($item);
    }

    public function createFile(CreateFile $command): ItemSummary
    {
        $item = $this->mapper->toFile($command, $this->folderParent($command->parent()));

        $this->flushCreating($item);

        return $this->mapper->toSummary($item);
    }

    public function rename(Uuid $id, RenameItem $command): ItemSummary
    {
        $item = $this->requireItem($id);
        $this->guardNotRoot($item);

        $this->mapper->applyRename($item, $command);
        $this->flushAtomically();

        return $this->mapper->toSummary($item);
    }

    public function delete(Uuid $id): void
    {
        $item = $this->requireItem($id);
        $this->guardNotRoot($item);

        $this->entityManager->remove($item);
        $this->flushAtomically();
    }

    public function listChildren(Uuid $folderId, PaginationQuery $query): Page
    {
        $folder = $this->requireFolder($folderId);
        $children = $this->repository->findChildren($folder->getId(), $query->limit, $query->offset);

        return $this->mapper->toSummaryPage($children, $query->limit, $query->offset);
    }

    public function getItem(Uuid $id): ItemDetail
    {
        $item = $this->requireItem($id);
        $ancestorPath = $this->repository->findAncestorPath($item->getId());

        return $this->mapper->toDetail($item, $ancestorPath);
    }

    public function rootFolder(): ItemSummary
    {
        return $this->mapper->toSummary($this->root());
    }

    public function search(SearchQuery $query): Page
    {
        $scopeFolderId = SearchScope::Folder === $query->scope
            ? $this->requireFolder($query->folderScopeId())->getId()
            : null;
        $matches = $this->repository->findByExactName($query->name, $scopeFolderId, $query->limit, $query->offset);

        return $this->mapper->toDetailPage($matches, $query->limit, $query->offset);
    }

    public function suggestions(SuggestionQuery $query): SuggestionList
    {
        $matches = $this->repository->findSuggestionsByPrefix($query->prefix, self::SUGGESTION_LIMIT);

        return $this->mapper->toSuggestionList($matches);
    }

    /**
     * Each write is a single flush, and the ORM wraps a flush in its own
     * transaction, so every write commits atomically (AD-5).
     */
    private function flushCreating(Item $item): void
    {
        $this->entityManager->persist($item);
        $this->flushAtomically();
    }

    private function flushAtomically(): void
    {
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new DuplicateItemNameException();
        }
    }

    private function root(): Item
    {
        $root = $this->repository->find(Uuid::fromString(Item::ROOT_ID));

        if (null === $root) {
            throw new ParentNotFoundException();
        }

        return $root;
    }

    private function folderParent(Uuid $parentId): Item
    {
        $parent = $this->repository->find($parentId);

        if (null === $parent) {
            throw new ParentNotFoundException();
        }

        if (ItemType::Folder !== $parent->getType()) {
            throw new InvalidParentTypeException();
        }

        return $parent;
    }

    private function requireItem(Uuid $id): Item
    {
        $item = $this->repository->find($id);

        if (null === $item) {
            throw new ItemNotFoundException();
        }

        return $item;
    }

    private function requireFolder(Uuid $id): Item
    {
        $item = $this->requireItem($id);

        // A file addressed as a folder does not exist (404); only a parent
        // given to a create call is a 400 instead (folderParent).
        if (ItemType::Folder !== $item->getType()) {
            throw new ItemNotFoundException();
        }

        return $item;
    }

    private function guardNotRoot(Item $item): void
    {
        if ($item->getId()->equals(Uuid::fromString(Item::ROOT_ID))) {
            throw new RootChangeForbiddenException();
        }
    }
}
