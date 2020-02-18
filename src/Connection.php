<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle;

use Foolz\SphinxQL\Drivers\MultiResultSetInterface;
use Foolz\SphinxQL\Drivers\ResultSetInterface;

class Connection extends \Foolz\SphinxQL\Drivers\Pdo\Connection
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function query($query): ResultSetInterface
    {
        $start = microtime(true);
        $result = parent::query($query);
        $time = microtime(true) - $start;

        $this->logger->logQuery($query, $time);

        return $result;
    }

    public function multiQuery(array $queue): MultiResultSetInterface
    {
        $start = microtime(true);
        $result = parent::multiQuery($queue);
        $time = microtime(true) - $start;

        $this->logger->logQuery(implode(';', $queue), $time);

        return $result;
    }
}
