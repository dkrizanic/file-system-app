<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class FunctionalTestCase extends WebTestCase
{
    private static bool $migrationsExecuted = false;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->boot();

        $registry = self::getContainer()->get('doctrine');
        \assert($registry instanceof ManagerRegistry);
        $connection = $registry->getConnection();
        \assert($connection instanceof Connection);
        $this->connection = $connection;

        if (!self::$migrationsExecuted) {
            \assert(self::$kernel instanceof KernelInterface);
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
            if (0 !== $exitCode) {
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

    protected function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        \assert($entityManager instanceof EntityManagerInterface);

        return $entityManager;
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
