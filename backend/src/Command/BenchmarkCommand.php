<?php

declare(strict_types=1);

namespace App\Command;

use App\Contract\ItemServiceInterface;
use App\DTO\Write\CreateFolder;
use App\DTO\Write\SuggestionQuery;
use App\Entity\Item;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Measures the two performance targets from the PRD's NFR-1/NFR-2 against a
 * real database: suggestion latency on a 100,000-file corpus where every file
 * shares one prefix (the worst case), and a 10,101-item atomic cascade
 * delete. Point DATABASE_URL at the throwaway db-test database when running
 * it — see the README's benchmark section.
 */
#[AsCommand(name: 'app:benchmark', description: 'Measure suggestion latency and cascade delete against the NFR targets')]
final class BenchmarkCommand extends Command
{
    private const SUGGESTION_RUNS = 50;

    private const DELETE_ROUNDS = 3;

    private const CHAIN_DEPTH = 15;

    private const BUCKETS = 100;

    private const FILES_PER_BUCKET = 1000;

    private const DELETE_FOLDERS = 100;

    private const FILES_PER_DELETE_FOLDER = 100;

    private const SUGGESTION_TARGET_MS = 200.0;

    private const DELETE_TARGET_MS = 10000.0;

    public function __construct(
        private readonly ItemServiceInterface $items,
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->runsAgainstTestDatabase()) {
            $output->writeln(sprintf(
                '<error>Refusing to run against database "%s": this command deletes every item.</error> Point DATABASE_URL at the db-test container as shown in the README.',
                $this->connection->getDatabase() ?? 'unknown',
            ));

            return Command::FAILURE;
        }

        $this->ensureSchema($output);
        $this->wipe();

        $output->writeln('Seeding the suggestion corpus...');
        $seedMs = $this->time(fn () => $this->seedCorpus());
        $output->writeln(sprintf(
            '  %s items (%d files sharing the prefix "report"), seeded in %s ms',
            number_format($this->countItems()),
            self::BUCKETS * self::FILES_PER_BUCKET,
            number_format($seedMs),
        ));

        $suggestionTimes = [];
        for ($i = 0; $i < self::SUGGESTION_RUNS; ++$i) {
            $suggestionTimes[] = $this->time(fn (): int => count($this->items->suggestions(new SuggestionQuery('report'))->items));
        }

        $deleteTimes = [];
        for ($round = 1; $round <= self::DELETE_ROUNDS; ++$round) {
            $doomed = $this->seedDoomedSubtree();
            $this->entityManager->clear();
            $deleteTimes[] = $this->time(fn () => $this->items->delete($doomed));
        }
        $this->wipe();

        $p50 = $this->percentile($suggestionTimes, 0.50);
        $p95 = $this->percentile($suggestionTimes, 0.95);
        $suggestionPass = $p95 <= self::SUGGESTION_TARGET_MS;
        $output->writeln(sprintf(
            'Suggestions "report" x%d:   p50 %s ms · p95 %s ms · max %s ms   (target ~%s ms p95) %s',
            self::SUGGESTION_RUNS,
            number_format($p50, 1),
            number_format($p95, 1),
            number_format(max($suggestionTimes), 1),
            number_format(self::SUGGESTION_TARGET_MS),
            $suggestionPass ? 'PASS' : 'FAIL',
        ));

        $output->writeln(sprintf(
            'Cascade delete %s items x%d: %s   (target %s s) %s',
            number_format(1 + self::DELETE_FOLDERS * (1 + self::FILES_PER_DELETE_FOLDER)),
            self::DELETE_ROUNDS,
            implode(' / ', array_map(static fn (float $ms): string => number_format($ms, 1).' ms', $deleteTimes)),
            number_format(self::DELETE_TARGET_MS / 1000),
            max($deleteTimes) <= self::DELETE_TARGET_MS ? 'PASS' : 'FAIL',
        ));

        return $suggestionPass && max($deleteTimes) <= self::DELETE_TARGET_MS
            ? Command::SUCCESS
            : Command::FAILURE;
    }

    private function ensureSchema(OutputInterface $output): void
    {
        if (\is_string($this->connection->fetchOne("SELECT to_regclass('public.item')"))) {
            return;
        }

        $output->writeln('Schema missing — running migrations...');
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $exitCode = $application->run(
            new ArrayInput([
                'command' => 'doctrine:migrations:migrate',
                '--no-interaction' => true,
                '--allow-no-migration' => true,
            ]),
            new NullOutput(),
        );
        if (0 !== $exitCode) {
            throw new \RuntimeException('Preparing the benchmark database failed.');
        }
    }

    private function seedCorpus(): void
    {
        $chainIds = [];
        $parentId = null;
        for ($level = 1; $level <= self::CHAIN_DEPTH; ++$level) {
            $parentId = $this->items->createFolder(new CreateFolder($parentId, sprintf('level-%02d', $level)))->id;
            $chainIds[] = $parentId;
        }

        for ($bucket = 1; $bucket <= self::BUCKETS; ++$bucket) {
            $host = $chainIds[2 + ($bucket % 12)];
            $this->items->createFolder(new CreateFolder($host, sprintf('bucket-%03d', $bucket)));
        }

        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO item (id, parent_id, type, name, normalized_name)
            SELECT gen_random_uuid(), b.id, 'file',
                   'report-' || lpad(b.n::text, 3, '0') || '-' || lpad(g::text, 4, '0'),
                   'report-' || lpad(b.n::text, 3, '0') || '-' || lpad(g::text, 4, '0')
            FROM (
                SELECT i.id, row_number() OVER (ORDER BY i.normalized_name) AS n
                FROM item i
                WHERE i.type = 'folder' AND i.normalized_name LIKE 'bucket-%'
            ) b
            CROSS JOIN generate_series(1, :filesPerBucket) AS g
            SQL,
            ['filesPerBucket' => self::FILES_PER_BUCKET],
            ['filesPerBucket' => ParameterType::INTEGER],
        );
    }

    private function seedDoomedSubtree(): Uuid
    {
        $root = $this->items->createFolder(new CreateFolder(null, 'doomed'))->id;
        for ($folder = 1; $folder <= self::DELETE_FOLDERS; ++$folder) {
            $this->items->createFolder(new CreateFolder($root, sprintf('doomed-%03d', $folder)));
        }

        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO item (id, parent_id, type, name, normalized_name)
            SELECT gen_random_uuid(), f.id, 'file',
                   'doomed-report-' || lpad(f.n::text, 3, '0') || '-' || lpad(g::text, 4, '0'),
                   'doomed-report-' || lpad(f.n::text, 3, '0') || '-' || lpad(g::text, 4, '0')
            FROM (
                SELECT i.id, row_number() OVER (ORDER BY i.normalized_name) AS n
                FROM item i
                WHERE i.type = 'folder' AND i.normalized_name LIKE 'doomed-%'
            ) f
            CROSS JOIN generate_series(1, :filesPerFolder) AS g
            SQL,
            ['filesPerFolder' => self::FILES_PER_DELETE_FOLDER],
            ['filesPerFolder' => ParameterType::INTEGER],
        );

        return $root;
    }

    private function wipe(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM item WHERE id <> :root',
            ['root' => Item::ROOT_ID],
            ['root' => 'uuid'],
        );
    }

    private function runsAgainstTestDatabase(): bool
    {
        $database = $this->connection->getDatabase();

        return \is_string($database) && str_contains(strtolower($database), 'test');
    }

    private function countItems(): int
    {
        $count = $this->connection->fetchOne('SELECT count(*) FROM item');
        \assert(\is_numeric($count));

        return (int) $count;
    }

    private function time(callable $fn): float
    {
        $start = hrtime(true);
        $fn();

        return (hrtime(true) - $start) / 1e6;
    }

    /**
     * @param list<float> $times
     */
    private function percentile(array $times, float $fraction): float
    {
        sort($times);
        $index = (int) ceil($fraction * count($times)) - 1;

        return $times[max(0, $index)];
    }
}
