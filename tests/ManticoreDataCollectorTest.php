<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Versh23\ManticoreBundle\Logger;
use Versh23\ManticoreBundle\ManticoreDataCollector;

class ManticoreDataCollectorTest extends TestCase
{
    public function testCountQueries()
    {
        $total = 3;

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())
            ->method('getNbQueries')
            ->willReturn($total);

        $requestMock = $this->createMock(Request::class);
        $responseMock = $this->createMock(Response::class);

        $collector = new ManticoreDataCollector($logger);
        $collector->collect($requestMock, $responseMock);

        $this->assertSame($total, $collector->getQueryCount());
    }

    public function testQueries()
    {
        $queries = [
            ['query' => 'show meta', 'time' => 200],
            ['query' => 'show meta', 'time' => 300],
        ];

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())
            ->method('getQueries')
            ->willReturn($queries);

        $requestMock = $this->createMock(Request::class);
        $responseMock = $this->createMock(Response::class);

        $collector = new ManticoreDataCollector($logger);
        $collector->collect($requestMock, $responseMock);

        $this->assertSame($queries, $collector->getQueries());
    }

    public function testQueriesTime()
    {
        $queries = [
            ['query' => 'show meta', 'time' => 200],
            ['query' => 'show meta', 'time' => 300],
        ];

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())
            ->method('getQueries')
            ->willReturn($queries);

        $requestMock = $this->createMock(Request::class);
        $responseMock = $this->createMock(Response::class);

        $collector = new ManticoreDataCollector($logger);
        $collector->collect($requestMock, $responseMock);

        $this->assertSame(500.0, $collector->getTime());
    }

    public function testReset()
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())
            ->method('reset');

        $requestMock = $this->createMock(Request::class);
        $responseMock = $this->createMock(Response::class);

        $collector = new ManticoreDataCollector($logger);
        $collector->collect($requestMock, $responseMock);
        $collector->reset();

        $this->assertSame([], $this->getProtectedProperty($collector, 'data'));
    }
}
