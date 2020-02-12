<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests\Command;

use Pagerfanta\Pagerfanta;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Versh23\ManticoreBundle\Command\PopulateCommand;
use Versh23\ManticoreBundle\IndexManagerRegistry;
use Versh23\ManticoreBundle\Tests\Entity\SimpleEntity;
use Versh23\ManticoreBundle\Tests\TestCase;

class PopulateCommandTest extends TestCase
{
    protected function createIndexManager(string $name, int $page = 1, int $limit = 100)
    {
        $manager = parent::createIndexManager($name);
        $manager->expects($this->once())->method('truncateIndex');
        $manager->expects($this->once())->method('flushIndex');
        $manager->expects($this->once())->method('bulkInsert')->with([
            new SimpleEntity(),
            new SimpleEntity(),
        ]);

        $pager = $this->createMock(Pagerfanta::class);
        $pager->expects($this->at(0))->method('setMaxPerPage')->with($limit);
        $pager->expects($this->at(1))->method('setCurrentPage')->with($page);
        $pager->method('getNbPages')->willReturn(1);
        $pager->method('getCurrentPage')->willReturn(1);
        $pager->method('getNbResults')->willReturn(2);
        $pager->method('getCurrentPageResults')->willReturn([
            new SimpleEntity(),
            new SimpleEntity(),
        ]);
        $manager
            ->method('createObjectPager')
            ->willReturn($pager)
        ;

        return $manager;
    }

    public function testPopulateSingleIndex()
    {
        $manager = $this->createIndexManager('index1');

        $registry = new IndexManagerRegistry();
        $registry->addIndexManager($manager);
        $registry->addIndexManager(parent::createIndexManager('index2'));

        $managerRegistry = $this->createManagerRegistry([]);

        $command = new PopulateCommand($registry, $managerRegistry);

        $command->run(new ArrayInput([
            'index' => 'index1',
        ]), new NullOutput());
    }

    public function testPopulateAllIndex()
    {
        $manager1 = $this->createIndexManager('index1');
        $manager2 = $this->createIndexManager('index2');

        $registry = new IndexManagerRegistry();
        $registry->addIndexManager($manager1);
        $registry->addIndexManager($manager2);

        $managerRegistry = $this->createManagerRegistry([]);

        $command = new PopulateCommand($registry, $managerRegistry);

        $command->run(new ArrayInput([
        ]), new NullOutput());
    }

    public function testPopulateWithParams()
    {
        $manager = $this->createIndexManager('index1', 2, 10);
        $registry = new IndexManagerRegistry();
        $registry->addIndexManager($manager);

        $managerRegistry = $this->createManagerRegistry([]);

        $command = new PopulateCommand($registry, $managerRegistry);

        $command->run(new ArrayInput([
            'index' => 'index1',
            '--page' => '2',
            '--limit' => '10',
        ]), new NullOutput());
    }
}
