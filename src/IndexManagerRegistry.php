<?php


namespace Versh23\ManticoreBundle;


class IndexManagerRegistry
{
    private $indexMap;
    private $classMap;

    public function addIndexManager(IndexManager $manager)
    {
        $index = $manager->getIndex();
        $this->indexMap[$index->getName()] = $manager;
        $this->classMap[$index->getClass()][] = $index->getName();
    }

    public function getClassByIndex(string $index): string
    {
        foreach ($this->classMap as $class => $indexes) {
            if (false !== array_search($index, $indexes)) {
                return $class;
            }
        }

        throw new \Exception('no class');
    }

    public function getIndexManager(string $index): IndexManager
    {
        return $this->indexMap[$index];
    }


}