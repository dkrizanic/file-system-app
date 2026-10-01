<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * PSR-3 logger plugged into DBAL's Logging middleware: DBAL reports every
 * query and statement execution as a debug message starting with
 * "Executing ", and nothing else carries that prefix.
 */
final class QueryCounter extends AbstractLogger implements \Countable
{
    /** @var int<0, max> */
    private int $queries = 0;

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (LogLevel::DEBUG === $level && str_starts_with((string) $message, 'Executing ')) {
            ++$this->queries;
        }
    }

    public function reset(): void
    {
        $this->queries = 0;
    }

    public function count(): int
    {
        return $this->queries;
    }
}
