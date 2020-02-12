<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests;

use Doctrine\Persistence\ManagerRegistry;
use Foolz\SphinxQL\Drivers\MultiResultSet;
use Foolz\SphinxQL\Drivers\Pdo\Connection;
use Foolz\SphinxQL\Drivers\ResultSet;
use Foolz\SphinxQL\Drivers\ResultSetInterface;
use Pagerfanta\Pagerfanta;
use Versh23\ManticoreBundle\Index;
use Versh23\ManticoreBundle\IndexManager;
use Versh23\ManticoreBundle\Tests\Entity\SimpleEntity;

class IndexManagerTest extends TestCase
{
    private function createIndex()
    {
        return new Index('test_index', SimpleEntity::class, [
            'name' => ['property' => 'name'],
        ], [
            'status' => ['property' => 'status', 'type' => 'string'],
        ]);
    }

    private function createConnection()
    {
        $connection = $this->createPartialMock(Connection::class, [
            'query', 'multiQuery',
        ]);
        $connection->setParams(['host' => $_SERVER['MANTICORE_HOST']]);

        return $connection;
    }

    public function testInsert()
    {
        $entity = new SimpleEntity();
        $entity->setId(1)->setStatus('enabled')->setName('name1');
        $index = $this->createIndex();

        $connection = $this->createConnection();
        $connection
            ->expects($this->once())
            ->method('query')
            ->with('INSERT INTO test_index (id, name, status) VALUES (1, \'name1\', \'enabled\')')
            ->willReturn($this->createMock(ResultSet::class));

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $indexManager = new IndexManager($connection, $index, $managerRegistry);
        $indexManager->insert($entity);
    }

    public function testReplace()
    {
        $entity = new SimpleEntity();
        $entity->setId(1)->setStatus('enabled')->setName('name1');

        $index = $this->createIndex();

        $connection = $this->createConnection();

        $connection
            ->expects($this->once())
            ->method('query')
            ->with('REPLACE INTO test_index (id, name, status) VALUES (1, \'name1\', \'enabled\')')
            ->willReturn($this->createMock(ResultSet::class));

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $indexManager = new IndexManager($connection, $index, $managerRegistry);
        $indexManager->replace($entity);
    }

    public function testBulkInsert()
    {
        $entity = new SimpleEntity();
        $entity->setId(1)->setStatus('enabled')->setName('name1');

        $entity2 = new SimpleEntity();
        $entity2->setId(2)->setStatus('disabled')->setName('name2');

        $index = $this->createIndex();

        $connection = $this->createConnection();

        $connection
            ->expects($this->once())
            ->method('query')
            ->with('INSERT INTO test_index (id, name, status) VALUES (1, \'name1\', \'enabled\'), (2, \'name2\', \'disabled\')')
            ->willReturn($this->createMock(ResultSet::class));

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $indexManager = new IndexManager($connection, $index, $managerRegistry);
        $indexManager->bulkInsert([$entity, $entity2]);
    }

    public function testFind()
    {
        $entity = new SimpleEntity();
        $entity->setId(1)->setStatus('enabled')->setName('name1');

        $entity2 = new SimpleEntity();
        $entity2->setId(2)->setStatus('disabled')->setName('name2');

        $index = $this->createIndex();

        $connection = $this->createConnection();

        $multiResultSet = $this->createMock(MultiResultSet::class);
        $resultSet = $this->createMock(ResultSetInterface::class);
        $resultSet
            ->expects($this->at(0))
            ->method('fetchAllAssoc')->willReturn([
            ['id' => 2],
            ['id' => 1],
        ]);

        $resultSet
            ->expects($this->at(1))
            ->method('fetchAllAssoc')->willReturn([
                ['Variable_name' => 'total_found', 'Value' => 10],
            ]);
        $multiResultSet->method('getNext')->willReturn($resultSet);
        $connection
            ->expects($this->once())
            ->method('multiQuery')
            ->with(['SELECT id, WEIGHT() as w FROM test_index WHERE MATCH(\'(@(name) test)\') ORDER BY w DESC LIMIT 0, 2', 'SHOW META'])
            ->willReturn($multiResultSet);

        $managerRegistry = $this->createManagerRegistry([$entity, $entity2]);
        $indexManager = new IndexManager($connection, $index, $managerRegistry);

        $result = $indexManager->find('test', 1, 2);

        $this->assertSame($entity2, $result[0]);
        $this->assertSame($entity, $result[1]);
    }

    public function testFindPaginated()
    {
        $entity = new SimpleEntity();
        $entity->setId(1)->setStatus('enabled')->setName('name1');

        $entity2 = new SimpleEntity();
        $entity2->setId(2)->setStatus('disabled')->setName('name2');

        $index = $this->createIndex();

        $connection = $this->createConnection();

        $multiResultSet = $this->createMock(MultiResultSet::class);
        $resultSet = $this->createMock(ResultSetInterface::class);
        $resultSet
            ->expects($this->at(0))
            ->method('fetchAllAssoc')->willReturn([
                ['id' => 2],
                ['id' => 1],
            ]);

        $resultSet
            ->expects($this->at(1))
            ->method('fetchAllAssoc')->willReturn([
                ['Variable_name' => 'total_found', 'Value' => 10],
            ]);
        $multiResultSet->method('getNext')->willReturn($resultSet);
        $connection
            ->expects($this->once())
            ->method('multiQuery')
            ->with(['SELECT id, WEIGHT() as w FROM test_index WHERE MATCH(\'(@(name) test)\') ORDER BY w DESC LIMIT 0, 2', 'SHOW META'])
            ->willReturn($multiResultSet);

        $managerRegistry = $this->createManagerRegistry([$entity, $entity2]);

        $indexManager = new IndexManager($connection, $index, $managerRegistry);

        $result = $indexManager->findPaginated('test', 1, 2);

        $this->assertInstanceOf(Pagerfanta::class, $result);
        $this->assertSame(10, $result->getNbResults());
        $this->assertSame($entity2, $result->getCurrentPageResults()[0]);
        $this->assertSame($entity, $result->getCurrentPageResults()[1]);
    }

    public function testFindSphinxQL()
    {
        $entity = new SimpleEntity();
        $entity->setId(1)->setStatus('enabled')->setName('name1');

        $entity2 = new SimpleEntity();
        $entity2->setId(2)->setStatus('disabled')->setName('name2');

        $index = $this->createIndex();

        $connection = $this->createConnection();

        $multiResultSet = $this->createMock(MultiResultSet::class);
        $resultSet = $this->createMock(ResultSetInterface::class);
        $resultSet
            ->expects($this->at(0))
            ->method('fetchAllAssoc')->willReturn([
                ['id' => 2],
                ['id' => 1],
            ]);

        $resultSet
            ->expects($this->at(1))
            ->method('fetchAllAssoc')->willReturn([
                ['Variable_name' => 'total_found', 'Value' => 10],
            ]);
        $multiResultSet->method('getNext')->willReturn($resultSet);
        $connection
            ->expects($this->once())
            ->method('multiQuery')
            ->with(['SELECT * FROM test_index WHERE status = \'enabled\' LIMIT 0, 2', 'SHOW META'])
            ->willReturn($multiResultSet);

        $managerRegistry = $this->createManagerRegistry([$entity, $entity2]);
        $indexManager = new IndexManager($connection, $index, $managerRegistry);

        $query = $indexManager->createQuery();
        $query->select('*')->from('test_index')->where('status', '=', 'enabled');

        $result = $indexManager->find($query, 1, 2);

        $this->assertSame($entity2, $result[0]);
        $this->assertSame($entity, $result[1]);
    }

    public function testFindSqphinxQLPaginated()
    {
        $entity = new SimpleEntity();
        $entity->setId(1)->setStatus('enabled')->setName('name1');

        $entity2 = new SimpleEntity();
        $entity2->setId(2)->setStatus('disabled')->setName('name2');

        $index = $this->createIndex();

        $connection = $this->createConnection();

        $multiResultSet = $this->createMock(MultiResultSet::class);
        $resultSet = $this->createMock(ResultSetInterface::class);
        $resultSet
            ->expects($this->at(0))
            ->method('fetchAllAssoc')->willReturn([
                ['id' => 2],
                ['id' => 1],
            ]);

        $resultSet
            ->expects($this->at(1))
            ->method('fetchAllAssoc')->willReturn([
                ['Variable_name' => 'total_found', 'Value' => 10],
            ]);
        $multiResultSet->method('getNext')->willReturn($resultSet);
        $connection
            ->expects($this->once())
            ->method('multiQuery')
            ->with(['SELECT * FROM test_index WHERE status = \'enabled\' LIMIT 0, 2', 'SHOW META'])
            ->willReturn($multiResultSet);

        $managerRegistry = $this->createManagerRegistry([$entity, $entity2]);

        $indexManager = new IndexManager($connection, $index, $managerRegistry);

        $query = $indexManager->createQuery();
        $query->select('*')->from('test_index')->where('status', '=', 'enabled');

        $result = $indexManager->findPaginated($query, 1, 2);

        $this->assertInstanceOf(Pagerfanta::class, $result);
        $this->assertSame(10, $result->getNbResults());
        $this->assertSame($entity2, $result->getCurrentPageResults()[0]);
        $this->assertSame($entity, $result->getCurrentPageResults()[1]);
    }
}
