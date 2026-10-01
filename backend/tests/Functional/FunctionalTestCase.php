<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Contract\ItemRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

abstract class FunctionalTestCase extends WebTestCase
{
    private static bool $migrationsExecuted = false;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->boot();

        $this->connection = self::getContainer()->get('doctrine')->getConnection();

        if (!self::$migrationsExecuted) {
            $application = new Application(self::$kernel);
            $application->setAutoExit(false);
            $exitCode = $application->run(
                new ArrayInput([
                    'command' => 'doctrine:migrations:migrate',
                    '--no-interaction' => true,
                    '--allow-no-migration' => true,
                ]),
                new NullOutput(),
            );
            if ($exitCode !== 0) {
                throw new \RuntimeException(sprintf('Preparing the test database failed (exit code %d).', $exitCode));
            }
            self::$migrationsExecuted = true;
        }

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    protected function connection(): Connection
    {
        return $this->connection;
    }

    protected function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        \assert($entityManager instanceof EntityManagerInterface);

        return $entityManager;
    }

    protected function repository(): ItemRepositoryInterface
    {
        $repository = self::getContainer()->get(ItemRepositoryInterface::class);
        \assert($repository instanceof ItemRepositoryInterface);

        return $repository;
    }

    protected function queryCounter(): QueryCounter
    {
        $counter = self::getContainer()->get(QueryCounter::class);
        \assert($counter instanceof QueryCounter);

        return $counter;
    }

    protected function boot(): void
    {
        self::bootKernel();
    }
}
