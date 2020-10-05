<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Manticoresearch\Index;
use Pagerfanta\Pagerfanta;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Versh23\ManticoreBundle\Command\PopulateCommand;
use Versh23\ManticoreBundle\IndexManager;
use Versh23\ManticoreBundle\IndexManagerRegistry;

class PopulateCommandTest extends TestCase
{
    public function testPopulateSingleIndex()
    {
        $em = $this->createMock(Registry::class);
        $indexRegistry = new IndexManagerRegistry();

        $indexManager = $this->createIndexManager('index1');
        $indexRegistry->addIndexManager($indexManager);

        $command = new PopulateCommand($indexRegistry, $em);
        $command->run(new ArrayInput([
            'index' => 'index1',
        ]), new NullOutput());
    }

    public function testPopulateAllIndexes()
    {
        $em = $this->createMock(Registry::class);
        $indexRegistry = new IndexManagerRegistry();

        $indexManager = $this->createIndexManager('index1');
        $indexRegistry->addIndexManager($indexManager);

        $indexManager = $this->createIndexManager('index2');
        $indexRegistry->addIndexManager($indexManager);

        $command = new PopulateCommand($indexRegistry, $em);
        $command->run(new ArrayInput([
        ]), new NullOutput());
    }

    private function createIndexManager($indexName)
    {
        $index = $this->createMock(Index::class);
        $index->method('getName')->willReturn($indexName);

        $pager = $this->createMock(Pagerfanta::class);
        $pager->method('getNbPages')->willReturn(2);
        $pager->method('getCurrentPage')->willReturn(1);
        $pager->method('getNbResults')->willReturn(1);
        $pager->method('getCurrentPageResults')->willReturn([]);

        $indexManager = $this->createMock(IndexManager::class);
        $indexManager->method('getIndex')->willReturn($index);
        $indexManager->expects($this->once())->method('truncateIndex');
        $indexManager->expects($this->once())->method('flush');
        $indexManager->expects($this->exactly(2))->method('bulkInsert')->with([]);
        $indexManager->expects($this->once())->method('createObjectPager')->willReturn($pager);

        return $indexManager;
    }
}
