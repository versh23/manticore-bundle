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
        $this->logger->logQuery($query);

        return parent::query($query);
    }

    public function multiQuery(array $queue): MultiResultSetInterface
    {
        foreach ($queue as $query) {
            $this->logger->logQuery($query);
        }

        return parent::multiQuery($queue);
    }
}
