<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle;

use Doctrine\Persistence\Event\LifecycleEventArgs;

class Listener
{
    public $scheduledForInsertion = [];
    public $scheduledForUpdate = [];
    public $scheduledForDeletion = [];

    private $indexManager;

    public function __construct(IndexManager $indexManager)
    {
        $this->indexManager = $indexManager;
    }

    public function postFlush()
    {
        $this->persistScheduled();
    }

    public function preRemove(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();

        if ($this->indexManager->isIndexable($entity)) {
            $this->scheduledForDeletion[] = $this->indexManager->getIdentityValue($entity);
        }
    }

    public function postUpdate(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();

        if ($this->indexManager->isIndexable($entity)) {
            $this->scheduledForUpdate[] = $entity;
        }
    }

    public function postPersist(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();

        if ($this->indexManager->isIndexable($entity)) {
            $this->scheduledForInsertion[] = $entity;
        }
    }

    private function persistScheduled()
    {
        if (count($this->scheduledForInsertion)) {
            $this->indexManager->bulkInsert($this->scheduledForInsertion);
            $this->scheduledForInsertion = [];
        }
        if (count($this->scheduledForUpdate)) {
            $this->indexManager->bulkReplace($this->scheduledForUpdate);
            $this->scheduledForUpdate = [];
        }
        if (count($this->scheduledForDeletion)) {
            $this->indexManager->delete($this->scheduledForDeletion);
            $this->scheduledForDeletion = [];
        }
    }
}
