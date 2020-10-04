<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests;

use Manticoresearch\Index;
use PHPUnit\Framework\TestCase;
use Versh23\ManticoreBundle\IndexManager;
use Versh23\ManticoreBundle\IndexManagerRegistry;
use Versh23\ManticoreBundle\ManticoreException;

class IndexManagerRegistryTest extends TestCase
{
    public function testAddIndexManager()
    {
        $manager = $this->createMock(IndexManager::class);
        $index = $this->createMock(Index::class);
        $index->method('getName')->willReturn('index');
        $manager->method('getIndex')->willReturn($index);

        $registry = new IndexManagerRegistry();
        $registry->addIndexManager($manager);

        $this->assertSame($manager, $registry->getIndexManager('index'));
        $this->assertSame([$manager], $registry->getAllIndexManagers());
    }

    public function testInvalidManagerName()
    {
        $registry = new IndexManagerRegistry();
        $this->expectExceptionMessage('no indexManager found by index index1');
        $this->expectException(ManticoreException::class);
        $registry->getIndexManager('index1');
    }
}
