<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Versh23\ManticoreBundle\Index;
use Versh23\ManticoreBundle\IndexManager;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function createManagerRegistry(array $result)
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);

        $repository = $this->createMock(EntityRepository::class);

        $builder = $this->createMock(QueryBuilder::class);
        $builder->method('expr')->willReturn(new Expr());
        $builder->method('andWhere')->willReturn($builder);

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($result);
        $builder->method('getQuery')->willReturn($query);

        $repository->method('createQueryBuilder')->willReturn($builder);
        $objectManager = $this->createMock(ObjectManager::class);
        $objectManager->method('getRepository')->willReturn($repository);
        $managerRegistry->method('getManagerForClass')->willReturn($objectManager);

        return $managerRegistry;
    }

    protected function createIndexManager(string $name)
    {
        $index = $this->createMock(Index::class);
        $index->expects($this->atLeastOnce())->method('getName')->willReturn($name);
        $index->method('getFields')
            ->willReturn(
                [
                    'field1' => 'field1',
                    'field2' => 'field2',
                ]
            )
        ;
        $index->method('getAttributes')
            ->willReturn(
                [
                    'attr1' => [
                        'type' => 'string',
                    ],
                    'attr2' => [
                        'type' => 'timestamp',
                    ],
                ]
            )
        ;

        $indexManager = $this->createMock(IndexManager::class);
        $indexManager->expects($this->atLeastOnce())->method('getIndex')->willReturn($index);

        return $indexManager;
    }
}
