<?php

declare(strict_types=1);

namespace App\Repository;

use App\Contract\ItemRepositoryInterface;
use App\Entity\Item;
use App\Service\NameNormalizer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class ItemRepository implements ItemRepositoryInterface
{
    private const ANCESTOR_PATH_SQL = <<<'SQL'
        WITH RECURSIVE ancestors AS (
            SELECT i.id, i.name, i.parent_id, 0 AS depth
            FROM item i
            WHERE i.id = (SELECT p.parent_id FROM item p WHERE p.id = :itemId)
            UNION ALL
            SELECT i.id, i.name, i.parent_id, a.depth + 1
            FROM item i
            JOIN ancestors a ON i.id = a.parent_id
        )
        SELECT id, name FROM ancestors ORDER BY depth DESC
        SQL;

    /**
     * The scope CTE resolves to the whole tree when :folderId is the root:
     * every item descends from it, so the global search shares one query
     * shape with the scoped one.
     */
    private const SEARCH_SQL = <<<'SQL'
        WITH RECURSIVE scope AS (
            SELECT i.id
            FROM item i
            WHERE i.id = :folderId
            UNION ALL
            SELECT i.id
            FROM item i
            JOIN scope s ON i.parent_id = s.id
        ),
        matched AS (
            SELECT i.id, i.name, i.normalized_name, i.parent_id, count(*) OVER () AS total
            FROM item i
            WHERE i.type = 'file'
              AND i.normalized_name = :normalized
              AND i.id IN (SELECT s.id FROM scope s)
            ORDER BY i.normalized_name, i.id
            LIMIT :limit OFFSET :offset
        ),
        chain AS (
            SELECT m.id AS matched_id, m.id AS node_id, m.parent_id AS node_parent_id, m.name AS node_name, 0 AS depth
            FROM matched m
            UNION ALL
            SELECT c.matched_id, p.id, p.parent_id, p.name, c.depth + 1
            FROM chain c
            JOIN item p ON p.id = c.node_parent_id
        )
        SELECT m.id AS match_id, m.name AS match_name, m.parent_id AS match_parent_id,
               m.total AS match_total, c.depth, c.node_id, c.node_name
        FROM chain c
        JOIN matched m ON m.id = c.matched_id
        ORDER BY m.normalized_name, m.id, c.depth DESC
        SQL;

    private const SUGGESTIONS_SQL = <<<'SQL'
        WITH RECURSIVE matched AS (
            SELECT i.id, i.name, i.normalized_name, i.parent_id
            FROM item i
            WHERE i.type = 'file' AND i.normalized_name LIKE :pattern ESCAPE '\'
            ORDER BY i.normalized_name, i.id
            LIMIT :limit
        ),
        chain AS (
            SELECT m.id AS matched_id, m.id AS node_id, m.parent_id AS node_parent_id, m.name AS node_name, 0 AS depth
            FROM matched m
            UNION ALL
            SELECT c.matched_id, p.id, p.parent_id, p.name, c.depth + 1
            FROM chain c
            JOIN item p ON p.id = c.node_parent_id
        )
        SELECT m.id AS match_id, m.name AS match_name, m.parent_id AS match_parent_id,
               c.depth, c.node_id, c.node_name
        FROM chain c
        JOIN matched m ON m.id = c.matched_id
        ORDER BY m.normalized_name, m.id, c.depth DESC
        SQL;

    /**
     * Same scope and filter as SEARCH_SQL, without the page window — the
     * fallback that keeps the true total when no row survives the offset.
     */
    private const SEARCH_COUNT_SQL = <<<'SQL'
        WITH RECURSIVE scope AS (
            SELECT i.id
            FROM item i
            WHERE i.id = :folderId
            UNION ALL
            SELECT i.id
            FROM item i
            JOIN scope s ON i.parent_id = s.id
        )
        SELECT count(*)
        FROM item i
        WHERE i.type = 'file'
          AND i.normalized_name = :normalized
          AND i.id IN (SELECT s.id FROM scope s)
        SQL;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NameNormalizer $normalizer,
    ) {
    }

    public function find(Uuid $id): ?Item
    {
        return $this->entityManager->find(Item::class, $id);
    }

    public function findChildren(Uuid $folderId, int $limit, int $offset): array
    {
        $items = $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(Item::class, 'i')
            ->where('i.parent = :folderId')
            ->setParameter('folderId', $folderId, 'uuid')
            ->orderBy('i.type', 'DESC')
            ->addOrderBy('i.normalizedName', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        $total = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Item::class, 'i')
            ->where('i.parent = :folderId')
            ->setParameter('folderId', $folderId, 'uuid')
            ->getQuery()
            ->getSingleScalarResult();

        return ['items' => $items, 'total' => $total];
    }

    public function findAncestorPath(Uuid $id): array
    {
        $rows = $this->connection()->fetchAllAssociative(
            self::ANCESTOR_PATH_SQL,
            ['itemId' => $id],
            ['itemId' => 'uuid'],
        );

        $path = [];
        foreach ($rows as $row) {
            $path[] = [
                'id' => Uuid::fromString((string) $row['id']),
                'name' => (string) $row['name'],
            ];
        }

        return $path;
    }

    public function findByExactName(string $name, ?Uuid $folderId, int $limit, int $offset): array
    {
        $folderId ??= Uuid::fromString(Item::ROOT_ID);
        $normalized = $this->normalizer->normalize($name);

        $rows = $this->connection()->fetchAllAssociative(self::SEARCH_SQL, [
            'folderId' => $folderId,
            'normalized' => $normalized,
            'limit' => $limit,
            'offset' => $offset,
        ], [
            'folderId' => 'uuid',
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ]);

        if ($rows === []) {
            $total = (int) $this->connection()->fetchOne(self::SEARCH_COUNT_SQL, [
                'folderId' => $folderId,
                'normalized' => $normalized,
            ], ['folderId' => 'uuid']);

            return ['items' => [], 'total' => $total];
        }

        return ['items' => $this->matchesFromRows($rows), 'total' => (int) $rows[0]['match_total']];
    }

    public function findSuggestionsByPrefix(string $prefix, int $limit = 10): array
    {
        if ($prefix === '') {
            return [];
        }

        $rows = $this->connection()->fetchAllAssociative(self::SUGGESTIONS_SQL, [
            'pattern' => $this->prefixPattern($prefix),
            'limit' => $limit,
        ], [
            'limit' => ParameterType::INTEGER,
        ]);

        return $this->matchesFromRows($rows);
    }

    private function connection(): Connection
    {
        return $this->entityManager->getConnection();
    }

    private function prefixPattern(string $prefix): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->normalizer->normalize($prefix));

        return $escaped.'%';
    }

    /**
     * Rows arrive grouped per match, ancestors first (depth descending).
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{id: Uuid, name: string, parentId: ?Uuid, path: list<array{id: Uuid, name: string}>}>
     */
    private function matchesFromRows(array $rows): array
    {
        $matches = [];

        foreach ($rows as $row) {
            $matchId = (string) $row['match_id'];

            if (!isset($matches[$matchId])) {
                $matches[$matchId] = [
                    'id' => Uuid::fromString($matchId),
                    'name' => (string) $row['match_name'],
                    'parentId' => $row['match_parent_id'] === null
                        ? null
                        : Uuid::fromString((string) $row['match_parent_id']),
                    'path' => [],
                ];
            }

            if ((int) $row['depth'] > 0) {
                $matches[$matchId]['path'][] = [
                    'id' => Uuid::fromString((string) $row['node_id']),
                    'name' => (string) $row['node_name'],
                ];
            }
        }

        return array_values($matches);
    }
}
