<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;

class Listener
{
    public $scheduledForInsertion = [];
    public $scheduledForUpdate = [];
    public $scheduledForDeletion = [];

    private $managerRegistry;

    public function __construct(IndexManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function postFlush()
    {
        $this->persistScheduled();
    }

    public function preRemove(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();

//        if ($this->objectPersister->handlesObject($entity)) {
//            $this->scheduleForDeletion($entity);
//        }
    }

    public function postUpdate(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();

//        if ($this->objectPersister->handlesObject($entity)) {
//            if ($this->isObjectIndexable($entity)) {
//                $this->scheduledForUpdate[] = $entity;
//            } else {
//                // Delete if no longer indexable
//                $this->scheduleForDeletion($entity);
//            }
//        }
    }

    public function postPersist(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();
//
//        if ($this->objectPersister->handlesObject($entity) && $this->isObjectIndexable($entity)) {
//            $this->scheduledForInsertion[] = $entity;
//        }
    }

    private function persistScheduled()
    {
//        if (count($this->scheduledForInsertion)) {
//            $this->objectPersister->insertMany($this->scheduledForInsertion);
//            $this->scheduledForInsertion = [];
//        }
//        if (count($this->scheduledForUpdate)) {
//            $this->objectPersister->replaceMany($this->scheduledForUpdate);
//            $this->scheduledForUpdate = [];
//        }
//        if (count($this->scheduledForDeletion)) {
//            $this->objectPersister->deleteManyByIdentifiers($this->scheduledForDeletion);
//            $this->scheduledForDeletion = [];
//        }
    }

//
//    private function scheduleForDeletion($object)
//    {
//        if ($identifierValue = $this->propertyAccessor->getValue($object, $this->config['identifier'])) {
//            $this->scheduledForDeletion[] = !is_scalar($identifierValue) ? (string) $identifierValue : $identifierValue;
//        }
//    }
}
