<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

class Logger extends AbstractLogger
{
    private $queries = [];
    private $debug;
    private $logger;

    public function __construct(LoggerInterface $logger = null, $debug = false)
    {
        $this->logger = $logger;
        $this->debug = $debug;
    }

    public function logQuery(string $query): void
    {
        if ($this->debug) {
            $this->queries[] = $query;
        }

        if ($this->logger) {
            $this->logger->info($query);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }

    public function reset(): void
    {
        $this->queries = [];
    }

    public function getNbQueries(): int
    {
        return count($this->queries);
    }

    public function getQueries(): array
    {
        return $this->queries;
    }
}
