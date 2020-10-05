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

    public function testPopulateWithPagerParams()
    {
        $em = $this->createMock(Registry::class);
        $indexRegistry = new IndexManagerRegistry();

        $indexManager = $this->createIndexManager('index1', 2, 10);
        $indexRegistry->addIndexManager($indexManager);

        $indexManager = $this->createIndexManager('index2', 2, 10);
        $indexRegistry->addIndexManager($indexManager);

        $command = new PopulateCommand($indexRegistry, $em);
        $command->run(new ArrayInput([
            '--page' => '2',
            '--limit' => '10',
        ]), new NullOutput());
    }

    public function testPopulateWithRecreateIndex()
    {
        $em = $this->createMock(Registry::class);
        $indexRegistry = new IndexManagerRegistry();

        $indexManager = $this->createIndexManager('index1', 1, 100, true);
        $indexRegistry->addIndexManager($indexManager);

        $command = new PopulateCommand($indexRegistry, $em);
        $command->run(new ArrayInput([
            '--recreate' => true,
        ]), new NullOutput());
    }

    private function createIndexManager(string $indexName, int $page = 1, int $limit = 100, bool $recreate = false)
    {
        $index = $this->createMock(Index::class);
        $index->method('getName')->willReturn($indexName);

        $pager = $this->createMock(Pagerfanta::class);
        $pager->expects($this->at(0))->method('setMaxPerPage')->with($limit);
        $pager->expects($this->at(1))->method('setCurrentPage')->with($page);
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

        if ($recreate) {
            $indexManager->expects($this->once())->method('createIndex')->with(true);
        } else {
            $indexManager->expects($this->never())->method('createIndex');
        }

        return $indexManager;
    }
}
