<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle;

class IndexManagerRegistry
{
    private $indexMap = [];

    public function addIndexManager(IndexManager $manager)
    {
        $index = $manager->getIndex();
        $indexName = $index->getName();
        $this->indexMap[$indexName] = $manager;
    }

    public function getIndexManager(string $index): IndexManager
    {
        if (!isset($this->indexMap[$index])) {
            throw new ManticoreException('no indexManager found by index '.$index);
        }

        return $this->indexMap[$index];
    }

    /**
     * @return IndexManager[]
     */
    public function getAllIndexManagers(): array
    {
        return array_values($this->indexMap);
    }
}
