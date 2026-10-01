<?php

declare(strict_types=1);

namespace App\Contract;

use App\Entity\Item;
use Symfony\Component\Uid\Uuid;

interface ItemRepositoryInterface
{
    public function find(Uuid $id): ?Item;

    /**
     * Children of the folder in listing order: folders first, then files,
     * each group by normalized name then id — stable across pages.
     *
     * @return array{items: list<Item>, total: int}
     */
    public function findChildren(Uuid $folderId, int $limit, int $offset): array;

    /**
     * Ancestors of the item, root first; the item itself is excluded.
     * The root's path is empty.
     *
     * @return list<array{id: Uuid, name: string}>
     */
    public function findAncestorPath(Uuid $id): array;

    /**
     * Files whose normalized name equals the normalized form of $name.
     * A null folder scope searches the whole tree; a non-null one only
     * that folder's subtree. Each match carries its ancestor path,
     * root first.
     *
     * @return array{items: list<array{id: Uuid, name: string, parentId: ?Uuid, path: list<array{id: Uuid, name: string}>}>, total: int}
     */
    public function findByExactName(string $name, ?Uuid $folderId, int $limit, int $offset): array;

    /**
     * Files whose normalized name starts with the normalized $prefix,
     * ordered by normalized name then id, at most $limit, each with its
     * ancestor path, root first. A blank prefix matches nothing.
     *
     * @return list<array{id: Uuid, name: string, parentId: ?Uuid, path: list<array{id: Uuid, name: string}>}>
     */
    public function findSuggestionsByPrefix(string $prefix, int $limit = 10): array;
}
