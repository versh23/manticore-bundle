<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests;

use Psr\Log\LoggerInterface;
use Versh23\ManticoreBundle\Logger;

class LoggerTest extends TestCase
{
    public function testNoQuery()
    {
        $logger = new Logger(null, false);
        $this->assertSame(0, $logger->getNbQueries());
    }

    public function testQueryWithDebug()
    {
        $logger = new Logger(null, true);
        $logger->logQuery('select 1', 100);
        $this->assertSame(1, $logger->getNbQueries());
    }

    public function testQueryWithNoDebug()
    {
        $logger = new Logger(null, false);
        $logger->logQuery('select 1', 100);
        $this->assertSame(0, $logger->getNbQueries());
    }

    public function testQueryFormat()
    {
        $logger = new Logger(null, true);
        $logger->logQuery('select 1', 100);
        $this->assertSame([
            'query' => 'select 1',
            'time' => 100.0,
        ], $logger->getQueries()[0]);
    }

    public function testQueryLogger()
    {
        $psrLogger = $this->createMock(LoggerInterface::class);

        $psrLogger->expects($this->once())->method('info')->with('select 1 100.00 ms');

        $logger = new Logger($psrLogger, true);
        $logger->logQuery('select 1', 100);
        $this->assertSame([
            'query' => 'select 1',
            'time' => 100.0,
        ], $logger->getQueries()[0]);
    }
}
