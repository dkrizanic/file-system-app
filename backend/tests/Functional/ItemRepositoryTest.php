<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Item;
use App\Entity\ItemType;
use App\Service\NameNormalizer;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Uid\Uuid;

final class ItemRepositoryTest extends FunctionalTestCase
{
    #[Test]
    #[TestDox('Persisting an item stores the case-folded, NFC-normalized name')]
    public function persistItemStoresNormalizedName(): void
    {
        $folder = $this->createFolder('Work', $this->root());

        $file = $this->createFile('Notes', $folder);
        $combined = $this->createFile("A\u{0301}rchive", $folder);

        $notesRow = $this->connection()->fetchAssociative(
            'SELECT name, normalized_name FROM item WHERE id = ?',
            [$file->getId()->toRfc4122()],
        );
        $combinedRow = $this->connection()->fetchAssociative(
            'SELECT name, normalized_name FROM item WHERE id = ?',
            [$combined->getId()->toRfc4122()],
        );

        self::assertIsArray($notesRow);
        self::assertIsArray($combinedRow);
        self::assertSame('Notes', $notesRow['name']);
        self::assertSame('notes', $notesRow['normalized_name']);
        self::assertSame("\u{00E1}rchive", $combinedRow['normalized_name']);
    }

    #[Test]
    #[TestDox('A sibling whose name differs only in casing violates the unique constraint')]
    public function duplicateSiblingNameViolatesUniqueConstraint(): void
    {
        $folder = $this->createFolder('A', $this->root());
        $this->createFolder('notes', $folder);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createFile('Notes', $folder);
    }

    #[Test]
    #[TestDox('Children are listed folders-first, then by normalized name, in two constant queries')]
    public function findChildrenOrdersFoldersFirstThenNormalizedName(): void
    {
        $folder = $this->createFolder('A', $this->root());
        $this->createFile('zebra', $folder);
        $this->createFolder('Zeta', $folder);
        $this->createFile('Apple', $folder);
        $this->createFolder('alpha', $folder);

        $this->queryCounter()->reset();
        $page = $this->repository()->findChildren($folder->getId(), 50, 0);
        self::assertCount(2, $this->queryCounter());

        $labels = array_map(
            static fn (Item $item): string => $item->getType()->value.' '.$item->getName(),
            $page['items'],
        );

        self::assertSame(
            ['folder alpha', 'folder Zeta', 'file Apple', 'file zebra'],
            $labels,
        );
        self::assertSame(4, $page['total']);
        self::assertEquals($folder->getId(), $page['items'][0]->getParentId());
    }

    #[Test]
    #[TestDox('Children pages are stable and an offset past the end is empty with the right total')]
    public function findChildrenPaginatesStablyAcrossPages(): void
    {
        $folder = $this->createFolder('A', $this->root());
        $names = [];
        for ($i = 1; $i <= 15; ++$i) {
            $name = sprintf('f%02d', $i);
            $names[] = $name;
            $this->createFile($name, $folder);
        }

        $all = $this->repository()->findChildren($folder->getId(), 100, 0);
        $pageOne = $this->repository()->findChildren($folder->getId(), 6, 0);
        $pageTwo = $this->repository()->findChildren($folder->getId(), 6, 6);
        $pageThree = $this->repository()->findChildren($folder->getId(), 6, 12);
        $pastEnd = $this->repository()->findChildren($folder->getId(), 6, 100);

        self::assertSame($names, $this->namesOf($all));
        self::assertSame(\array_slice($names, 0, 6), $this->namesOf($pageOne));
        self::assertSame(\array_slice($names, 6, 6), $this->namesOf($pageTwo));
        self::assertSame(\array_slice($names, 12, 3), $this->namesOf($pageThree));
        self::assertSame([], $pastEnd['items']);
        self::assertSame(15, $pastEnd['total']);
    }

    #[Test]
    #[TestDox('The ancestor path is root-first, excludes the item itself, in one query')]
    public function findAncestorPathReturnsRootFirstExcludingItem(): void
    {
        $levelOne = $this->createFolder('level-1', $this->root());
        $levelTwo = $this->createFolder('level-2', $levelOne);
        $leaf = $this->createFile('leaf.txt', $levelTwo);

        $this->queryCounter()->reset();
        $path = $this->repository()->findAncestorPath($leaf->getId());
        self::assertCount(1, $this->queryCounter());

        self::assertEquals(
            [
                ['id' => $this->rootId(), 'name' => 'Root'],
                ['id' => $levelOne->getId(), 'name' => 'level-1'],
                ['id' => $levelTwo->getId(), 'name' => 'level-2'],
            ],
            $path,
        );
    }

    #[Test]
    #[TestDox('The root has no ancestors')]
    public function findAncestorPathOfRootIsEmpty(): void
    {
        self::assertSame([], $this->repository()->findAncestorPath($this->rootId()));
    }

    #[Test]
    #[TestDox('Suggestions cap at 10 files ordered by normalized name, folders never match, in one query')]
    public function findSuggestionsReturnsTopFilesInOrder(): void
    {
        $folder = $this->createFolder('reports', $this->root());
        $expectedIds = [];
        for ($i = 1; $i <= 30; ++$i) {
            $file = $this->createFile(sprintf('rep-%02d', $i), $folder);
            if ($i <= 10) {
                $expectedIds[] = $file->getId()->toRfc4122();
            }
        }
        $this->createFile('unrelated', $folder);

        $this->queryCounter()->reset();
        $suggestions = $this->repository()->findSuggestionsByPrefix('Rep');
        self::assertCount(1, $this->queryCounter());

        self::assertSame($expectedIds, array_map(
            static fn (array $match): string => $match['id']->toRfc4122(),
            $suggestions,
        ));
        self::assertEquals(
            array_fill(0, 10, $folder->getId()),
            array_map(static fn (array $match): Uuid => $match['path'][1]['id'], $suggestions),
        );
    }

    #[Test]
    #[TestDox('A blank prefix matches nothing')]
    public function findSuggestionsWithBlankPrefixReturnsNothing(): void
    {
        $folder = $this->createFolder('A', $this->root());
        $this->createFile('report', $folder);

        self::assertSame([], $this->repository()->findSuggestionsByPrefix(''));
    }

    #[Test]
    #[TestDox('LIKE wildcards in the prefix are matched literally')]
    public function findSuggestionsEscapesLikeWildcards(): void
    {
        $folder = $this->createFolder('A', $this->root());
        $underscore = $this->createFile('a_b', $folder);
        $this->createFile('ab', $folder);
        $this->createFile('axb', $folder);
        $percent = $this->createFile('a%c', $folder);

        $byUnderscore = $this->repository()->findSuggestionsByPrefix('a_');
        $byPercent = $this->repository()->findSuggestionsByPrefix('a%');

        self::assertEquals([$underscore->getId()], $this->idsOf($byUnderscore));
        self::assertEquals([$percent->getId()], $this->idsOf($byPercent));
    }

    #[Test]
    #[TestDox('Deleting a folder removes its whole subtree in one flush')]
    public function deleteFolderCascadesToEntireSubtree(): void
    {
        $folder = $this->createFolder('A', $this->root());
        $subFolder = $this->createFolder('sub', $folder);
        $file = $this->createFile('deep.txt', $subFolder);

        $this->entityManager()->remove($folder);
        $this->entityManager()->flush();
        // The subtree dies in the database via the foreign key, outside the
        // entity manager's knowledge — clear the identity map before asking it.
        $this->entityManager()->clear();

        $survivors = $this->connection()->fetchOne(
            'SELECT count(*) FROM item WHERE id IN (:ids)',
            ['ids' => [
                $folder->getId()->toRfc4122(),
                $subFolder->getId()->toRfc4122(),
                $file->getId()->toRfc4122(),
            ]],
            ['ids' => ArrayParameterType::STRING],
        );

        self::assertIsNumeric($survivors);
        self::assertSame(0, (int) $survivors);
    }

    #[Test]
    #[TestDox('The root row is seeded with the fixed id and a NULL parent')]
    public function rootRowIsSeededWithFixedIdAndNullParent(): void
    {
        $root = $this->repository()->find($this->rootId());

        self::assertNotNull($root);
        self::assertSame(ItemType::Folder, $root->getType());
        self::assertSame('Root', $root->getName());
        self::assertNull($root->getParentId());
    }

    #[Test]
    #[TestDox('The partial unique index blocks a second NULL-parent row')]
    public function partialUniqueIndexBlocksSecondRoot(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        $this->connection()->executeStatement(
            "INSERT INTO item (id, parent_id, type, name, normalized_name) VALUES (?, NULL, 'folder', 'Other root', 'other root')",
            [Uuid::v7()->toRfc4122()],
        );
    }

    #[Test]
    #[TestDox('Exact search matches files case-insensitively across the whole tree, with paths, in one query')]
    public function findByExactNameMatchesFilesWithPathsGlobally(): void
    {
        $folderA = $this->createFolder('A', $this->root());
        $folderB = $this->createFolder('B', $this->root());
        $inA = $this->createFile('Notes', $folderA);
        $inB = $this->createFile('notes', $folderB);

        $this->queryCounter()->reset();
        $page = $this->repository()->findByExactName('NOTES', null, 50, 0);
        self::assertCount(1, $this->queryCounter());

        self::assertSame(2, $page['total']);
        $matchesById = [];
        foreach ($page['items'] as $match) {
            $matchesById[$match['id']->toRfc4122()] = $match;
        }

        $expectedIds = [$inA->getId()->toRfc4122(), $inB->getId()->toRfc4122()];
        $actualIds = array_keys($matchesById);
        sort($expectedIds);
        sort($actualIds);
        self::assertSame($expectedIds, $actualIds);

        $matchA = $matchesById[$inA->getId()->toRfc4122()];
        self::assertEquals($folderA->getId(), $matchA['parentId']);
        self::assertEquals(
            [
                ['id' => $this->rootId(), 'name' => 'Root'],
                ['id' => $folderA->getId(), 'name' => 'A'],
            ],
            $matchA['path'],
        );
    }

    #[Test]
    #[TestDox('Exact search reports the full total while returning one page')]
    public function findByExactNamePaginatesWithFullTotal(): void
    {
        $folder = $this->createFolder('A', $this->root());
        $this->createFile('dup', $folder);
        $this->createFile('dup', $this->root());

        $page = $this->repository()->findByExactName('dup', null, 1, 0);

        self::assertCount(1, $page['items']);
        self::assertSame(2, $page['total']);
    }

    #[Test]
    #[TestDox('A folder scope restricts exact search to that subtree, at any depth')]
    public function findByExactNameScopesToFolderSubtree(): void
    {
        $scope = $this->createFolder('scope', $this->root());
        $inside = $this->createFile('dup', $scope);
        $nestedFolder = $this->createFolder('nested', $scope);
        $nested = $this->createFile('dup', $nestedFolder);
        $outside = $this->createFolder('outside', $this->root());
        $this->createFile('dup', $outside);

        $page = $this->repository()->findByExactName('dup', $scope->getId(), 50, 0);

        $expectedIds = [$inside->getId()->toRfc4122(), $nested->getId()->toRfc4122()];
        $actualIds = array_map(
            static fn (array $match): string => $match['id']->toRfc4122(),
            $page['items'],
        );
        sort($expectedIds);
        sort($actualIds);
        self::assertSame($expectedIds, $actualIds);
        self::assertSame(2, $page['total']);

        $matchesById = [];
        foreach ($page['items'] as $match) {
            $matchesById[$match['id']->toRfc4122()] = $match;
        }
        self::assertEquals(
            [
                ['id' => $this->rootId(), 'name' => 'Root'],
                ['id' => $scope->getId(), 'name' => 'scope'],
                ['id' => $nestedFolder->getId(), 'name' => 'nested'],
            ],
            $matchesById[$nested->getId()->toRfc4122()]['path'],
        );
    }

    #[Test]
    #[TestDox('A search offset past the end returns an empty page with the full total')]
    public function findByExactNameOffsetPastEndReturnsEmptyPageWithFullTotal(): void
    {
        $folder = $this->createFolder('A', $this->root());
        $this->createFile('dup', $folder);
        $this->createFile('dup', $this->root());

        $page = $this->repository()->findByExactName('dup', null, 50, 5);

        self::assertSame([], $page['items']);
        self::assertSame(2, $page['total']);
    }

    #[Test]
    #[TestDox('Exact search never returns folders')]
    public function findByExactNameExcludesFolders(): void
    {
        $folder = $this->createFolder('report', $this->root());
        $file = $this->createFile('report', $folder);

        $page = $this->repository()->findByExactName('report', null, 50, 0);

        self::assertSame(1, $page['total']);
        self::assertSame(
            [$file->getId()->toRfc4122()],
            array_map(static fn (array $match): string => $match['id']->toRfc4122(), $page['items']),
        );
    }

    private function root(): Item
    {
        $root = $this->repository()->find($this->rootId());
        self::assertNotNull($root);

        return $root;
    }

    private function rootId(): Uuid
    {
        return Uuid::fromString(Item::ROOT_ID);
    }

    private function createFolder(string $name, Item $parent): Item
    {
        return $this->createItem($name, ItemType::Folder, $parent);
    }

    private function createFile(string $name, Item $parent): Item
    {
        return $this->createItem($name, ItemType::File, $parent);
    }

    private function createItem(string $name, ItemType $type, Item $parent): Item
    {
        $item = new Item($name, $type, $parent, $this->nameNormalizer());
        $this->entityManager()->persist($item);
        $this->entityManager()->flush();

        return $item;
    }

    private function nameNormalizer(): NameNormalizer
    {
        $normalizer = self::getContainer()->get(NameNormalizer::class);
        \assert($normalizer instanceof NameNormalizer);

        return $normalizer;
    }

    /**
     * @param array{items: list<Item>} $page
     *
     * @return list<string>
     */
    private function namesOf(array $page): array
    {
        return array_map(
            static fn (Item $item): string => $item->getName(),
            $page['items'],
        );
    }

    /**
     * @param list<array{id: Uuid, ...}> $matches
     *
     * @return list<Uuid>
     */
    private function idsOf(array $matches): array
    {
        return array_map(
            static fn (array $match): Uuid => $match['id'],
            $matches,
        );
    }
}
