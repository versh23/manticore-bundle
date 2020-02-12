<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Versh23\ManticoreBundle\Index;
use Versh23\ManticoreBundle\IndexManager;
use Versh23\ManticoreBundle\Listener;
use Versh23\ManticoreBundle\Tests\Entity\SimpleEntity;
use Versh23\ManticoreBundle\Tests\Entity\SimpleEntity2;

class ListenerTest extends TestCase
{
    public function testCorrectListener()
    {
        $entityInsert = (new SimpleEntity())->setId(1);
        $entityUpdate = (new SimpleEntity())->setId(2);
        $entityDelete = (new SimpleEntity())->setId(3);
        $entityNotIndexable = (new SimpleEntity2())->setId(3);

        $index = $this->createMock(Index::class);
        $index->method('getClass')->willReturn(SimpleEntity::class);

        $indexManager = $this->createPartialMock(IndexManager::class,
            ['getIndex', 'bulkInsert', 'bulkReplace', 'delete']
        );
        $indexManager->method('getIndex')->willReturn($index);
        $listener = new Listener($indexManager);

        $event = $this->createMock(LifecycleEventArgs::class);
        $event->method('getObject')->willReturn($entityInsert);
        $listener->postPersist($event);

        $event = $this->createMock(LifecycleEventArgs::class);
        $event->method('getObject')->willReturn($entityNotIndexable);
        $listener->postPersist($event);

        $event = $this->createMock(LifecycleEventArgs::class);
        $event->method('getObject')->willReturn($entityDelete);
        $listener->preRemove($event);

        $event = $this->createMock(LifecycleEventArgs::class);
        $event->method('getObject')->willReturn($entityUpdate);
        $listener->postUpdate($event);

        $this->assertCount(1, $listener->scheduledForInsertion);
        $this->assertCount(1, $listener->scheduledForUpdate);
        $this->assertCount(1, $listener->scheduledForDeletion);

        $indexManager->expects($this->once())->method('bulkInsert')->with([$entityInsert]);
        $indexManager->expects($this->once())->method('bulkReplace')->with([$entityUpdate]);
        $indexManager->expects($this->once())->method('delete')->with([$entityDelete->getId()]);

        $listener->postFlush();

        //twice flush
        $listener->postFlush();

        $this->assertCount(0, $listener->scheduledForInsertion);
        $this->assertCount(0, $listener->scheduledForUpdate);
        $this->assertCount(0, $listener->scheduledForDeletion);
    }
}
