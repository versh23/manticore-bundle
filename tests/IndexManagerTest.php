<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectManager;
use Manticoresearch\Client;
use Manticoresearch\Index;
use Manticoresearch\Query\BoolQuery;
use Manticoresearch\Query\QueryString;
use Manticoresearch\Response;
use Manticoresearch\ResultSet;
use Manticoresearch\Search;
use Pagerfanta\Pagerfanta;
use PHPUnit\Framework\TestCase;
use Versh23\ManticoreBundle\IndexConfiguration;
use Versh23\ManticoreBundle\IndexManager;
use Versh23\ManticoreBundle\Logger;

class IndexManagerTest extends TestCase
{
    private $client;
    private $indexConfig;
    private $em;
    private $logger;
    private $index;
    private $class;

    public function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->class = new class() {
            public $id;
            public $field1;
            public $field2;
        };
        $this->indexConfig = new IndexConfiguration('index', get_class($this->class), [
            'field1' => [
                'type' => 'text',
                'property' => 'field1',
                'options' => [],
            ],
            'field2' => [
                'type' => 'text',
                'property' => 'field2',
                'options' => ['indexed'],
            ],
        ], ['morphology' => 'stem_ru']);
        $this->em = $this->createMock(Registry::class);
        $this->logger = $this->createMock(Logger::class);
        $this->index = $this->createMock(Index::class);
        $this->client->method('index')->willReturn($this->index);

        parent::setUp();
    }

    private function createManager(): IndexManager
    {
        return new IndexManager($this->client, $this->indexConfig, $this->em, $this->logger);
    }

    public function testIsIndexable()
    {
        $indexManager = $this->createManager();
        $this->assertTrue($indexManager->isIndexable($this->class));
    }

    public function testCreateIndex()
    {
        $this->index->expects($this->never())->method('drop');
        $this->index
            ->expects($this->once())
            ->method('create')
            ->with([
                'field1' => [
                    'type' => 'text',
                    'options' => [],
                ],
                'field2' => [
                    'type' => 'text',
                    'options' => ['indexed'],
                ],
            ], ['morphology' => 'stem_ru']);
        $indexManager = $this->createManager();
        $indexManager->createIndex();
    }

    public function testReCreateIndex()
    {
        $this->index->expects($this->once())->method('drop');
        $this->index
            ->expects($this->once())
            ->method('create')
            ->with([
                'field1' => [
                    'type' => 'text',
                    'options' => [],
                ],
                'field2' => [
                    'type' => 'text',
                    'options' => ['indexed'],
                ],
            ], ['morphology' => 'stem_ru']);
        $indexManager = $this->createManager();
        $indexManager->createIndex(true);
    }

    public function testGetIndex()
    {
        $this->assertSame($this->index, $this->createManager()->getIndex());
    }

    public function testTruncateIndex()
    {
        $this->index->expects($this->once())->method('truncate');
        $this->createManager()->truncateIndex();
    }

    public function testFlush()
    {
        $this->index->expects($this->once())->method('flush');
        $this->createManager()->flush();
    }

    public function testDelete()
    {
        $this->index->expects($this->once())->method('deleteDocument')->with(1);
        $this->createManager()->delete(1);
    }

    public function testDeleteMultiple()
    {
        $this->index->expects($this->exactly(2))->method('deleteDocument')->withConsecutive([1], [2]);
        $this->createManager()->delete([1, 2]);
    }

    public function testBulkReplace()
    {
        $obj1 = clone $this->class;
        $obj2 = clone $this->class;

        $obj1->id = 1;
        $obj1->field1 = 'text';
        $obj1->field2 = 'text2';
        $obj2->id = 2;
        $obj2->field1 = 'text';
        $obj2->field2 = 'text2';

        $this->index->expects($this->once())->method('replaceDocuments')->with([
                [
                    'field1' => 'text',
                    'field2' => 'text2',
                    'id' => 1,
                ],
                [
                    'field1' => 'text',
                    'field2' => 'text2',
                    'id' => 2,
                ],
            ]
        );
        $this->createManager()->bulkReplace([$obj1, $obj2]);
    }

    public function testGetIdentityValue()
    {
        $obj1 = clone $this->class;

        $obj1->id = 1;
        $obj1->field1 = 'text';
        $obj1->field2 = 'text2';
        $this->assertSame(1, $this->createManager()->getIdentityValue($obj1));
    }

    public function testReplace()
    {
        $obj1 = clone $this->class;

        $obj1->id = 1;
        $obj1->field1 = 'text';
        $obj1->field2 = 'text2';

        $this->index->expects($this->once())->method('replaceDocument')->with(
            [
                'field1' => 'text',
                'field2' => 'text2',
            ], 1
        );

        $this->createManager()->replace($obj1);
    }

    public function testInsert()
    {
        $obj1 = clone $this->class;

        $obj1->id = 1;
        $obj1->field1 = 'text';
        $obj1->field2 = 'text2';

        $this->index->expects($this->once())->method('addDocument')->with(
            [
                'field1' => 'text',
                'field2' => 'text2',
            ], 1
        );

        $this->createManager()->insert($obj1);
    }

    public function testBulkInsert()
    {
        $obj1 = clone $this->class;
        $obj2 = clone $this->class;

        $obj1->id = 1;
        $obj1->field1 = 'text';
        $obj1->field2 = 'text2';
        $obj2->id = 2;
        $obj2->field1 = 'text';
        $obj2->field2 = 'text2';

        $this->index->expects($this->once())->method('addDocuments')->with([
                [
                    'field1' => 'text',
                    'field2' => 'text2',
                    'id' => 1,
                ],
                [
                    'field1' => 'text',
                    'field2' => 'text2',
                    'id' => 2,
                ],
            ]
        );
        $this->createManager()->bulkInsert([$obj1, $obj2]);
    }

    /** @dataProvider getFindQuery */
    public function testFind($query)
    {
        $result = new ResultSet(new Response('{"took": 0, "timed_out": 0}'));

        $search = $this->createMock(Search::class);
        $search->expects($this->once())->method('limit')->with(10)->willReturn($search);
        $search->expects($this->once())->method('offset')->with(10)->willReturn($search);
        $search->expects($this->once())->method('get')->willReturn($result);

        $this->index->expects($this->once())->method('search')->with($query)->willReturn($search);
        $this->assertSame([], $this->createManager()->find($query, 2, 10));
    }

    /** @dataProvider getFindQuery */
    public function testFindPaginated($query)
    {
        $result = new ResultSet(new Response('{"took": 0, "timed_out": 0}'));

        $search = $this->createMock(Search::class);
        $search->expects($this->once())->method('limit')->with(10)->willReturn($search);
        $search->expects($this->once())->method('offset')->with(0)->willReturn($search);
        $search->expects($this->once())->method('get')->willReturn($result);

        $this->index->expects($this->once())->method('search')->with($query)->willReturn($search);
        $pager = $this->createManager()->findPaginated($query, 1, 10);
        $this->assertInstanceOf(Pagerfanta::class, $pager);
        $this->assertCount(0, $pager);
        $this->assertSame(1, $pager->getCurrentPage());
    }

    public function testCreateObjectPager()
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())->method('createQueryBuilder')
            ->with('o')
            ->willReturn($this->createMock(QueryBuilder::class));
        $manager = $this->createMock(ObjectManager::class);
        $manager->method('getRepository')->willReturn($repo);
        $this->em->method('getManagerForClass')->willReturn($manager);
        $pager = $this->createManager()->createObjectPager();
        $this->assertInstanceOf(Pagerfanta::class, $pager);
    }

    public function getFindQuery()
    {
        $query = new BoolQuery();
        $query->must(new QueryString('find'));

        return [
            ['query'],
            [$query],
        ];
    }
}
