<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests;

use Versh23\ManticoreBundle\Connection;
use Versh23\ManticoreBundle\Logger;

class ConnectionTest extends TestCase
{
    public function testLogQuery()
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('logQuery')
            ->with('show meta');
        $connection = new Connection($logger);
        $connection->setParams(['host' => $_SERVER['MANTICORE_HOST']]);
        $connection->query('show meta');
    }

    public function testLogMultiQuery()
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('logQuery')
            ->with('show meta;show meta');
        $connection = new Connection($logger);
        $connection->setParams(['host' => $_SERVER['MANTICORE_HOST']]);
        $connection->multiQuery(['show meta', 'show meta']);
    }
}
