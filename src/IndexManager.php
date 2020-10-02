<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityRepository;
use Manticoresearch\Client;
use Manticoresearch\ResultSet;
use Pagerfanta\Adapter\FixedAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

// TODO add alter index
// TODO implement update index attributes
class IndexManager
{
    private const ALIAS = 'o';
    private const IDENTIFIER = 'id';

    private $index;

    private $propertyAccessor = null;
    private $managerRegistry;
    private $client;
    private $logger;

    public function __construct(Client $client, Index $index, Registry $managerRegistry, Logger $logger)
    {
        $this->index = $index;
        $this->managerRegistry = $managerRegistry;
        $this->client = $client;
        $this->logger = $logger;
    }

    public function isIndexable($object): bool
    {
        return get_class($object) === $this->getIndex()->getClass();
    }

    public function getIndex(): Index
    {
        return $this->index;
    }

    public function createIndex(bool $recreate = false): void
    {
        $index = $this->createManticoreIndex();

        if ($recreate) {
            $index->drop(true);
        }

        $fields = [];
        foreach ($this->index->getFields() as $fieldName => $field) {
            $fields[$fieldName] = [
                'type' => $field['type'],
                'options' => $field['options'],
            ];
        }
        $settings = $this->index->getOptions();

        $index->create($fields, $settings);
    }

    private function createManticoreIndex(): \Manticoresearch\Index
    {
        return $this->client->index($this->index->getName());
    }

    public function truncateIndex(): void
    {
        $this->createManticoreIndex()->truncate();
    }

    public function flush(): void
    {
        $this->createManticoreIndex()->flush();
    }

    public function delete($ids): void
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        if (!count($ids)) {
            return;
        }

        $index = $this->createManticoreIndex();
        foreach ($ids as $id) {
            $index->deleteDocument($id);
        }
    }

    public function bulkReplace(array $objects): void
    {
        $index = $this->createManticoreIndex();
        $documents = [];

        foreach ($objects as $object) {
            $documents[] = $this->createDocument($object);
        }

        $index->replaceDocuments($documents);
    }

    private function createDocument($object): array
    {
        $document = [];
        foreach ($this->index->getFields() as $name => $field) {
            $document[$name] = $this->getValue($object, $field['property'], $field['type']);
        }
        $document['id'] = $this->getIdentityValue($object);

        return $document;
    }

    // TODO object instead array

    private function getValue($object, string $property, string $type = Index::TYPE_TEXT)
    {
        $propertyAccessor = $this->getPropertyAccessor();
        $value = $propertyAccessor->getValue($object, $property);

        switch ($type) {
            case Index::TYPE_FLOAT:
                return (float) $value;
            case Index::TYPE_BOOL:
                return (bool) $value;
            case Index::TYPE_INTEGER:
                return (int) $value;
            case Index::TYPE_TIMESTAMP:
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->getTimestamp();
                }

                return (int) $value;
            case Index::TYPE_JSON:
                if (is_array($value)) {
                    $value = json_encode($value);
                }

                return (string) $value;
            case Index::TYPE_MULTI:
            case Index::TYPE_MULTI64:
                return (array) $value;
            case Index::TYPE_TEXT:
            case Index::TYPE_STRING:
            default:
                return (string) $value;
        }
    }

    private function getPropertyAccessor(): PropertyAccessor
    {
        if (null === $this->propertyAccessor) {
            $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        return $this->propertyAccessor;
    }

    public function getIdentityValue($object)
    {
        // TODO custom identity field
        return $this->getPropertyAccessor()->getValue($object, self::IDENTIFIER);
    }

    public function replace($object): void
    {
        $index = $this->createManticoreIndex();
        $document = $this->createDocument($object);
        $id = $document['id'];
        unset($document['id']);

        $index->replaceDocument($document, $id);
    }

    public function insert($object): void
    {
        $index = $this->createManticoreIndex();
        $document = $this->createDocument($object);
        $id = $document['id'];
        unset($document['id']);

        $index->addDocument($document, $id);
    }

    public function bulkInsert(array $objects): void
    {
        $index = $this->createManticoreIndex();
        $documents = [];

        foreach ($objects as $object) {
            $documents[] = $this->createDocument($object);
        }

        $index->addDocuments($documents);
    }

    public function find($query = '', int $page = 1, int $limit = 10): array
    {
        $items = [];
        $ids = [];

        $result = $this->doFind($query, $page, $limit);

        foreach ($result as $item) {
            $ids[] = $item->getId();
        }

        if (\count($ids) > 0) {
            $items = $this->hydrateItems($ids);
            $this->sort($ids, $items);
        }

        return $items;
    }

    private function doFind($query = '', int $page = 1, int $limit = 10): ResultSet
    {
        $index = $this->createManticoreIndex();
        $page = max($page, 1);
        $offset = ($page - 1) * $limit;

        return $this->collectData($index
            ->search($query)
            ->limit($limit)
            ->offset($offset)
            ->get());
    }

    private function hydrateItems(array $ids): array
    {
        $repository = $this->getRepository();

        $builder = $repository
            //TODO custom builder
            ->createQueryBuilder(self::ALIAS);

        $builder->andWhere($builder->expr()->in(self::ALIAS.'.'.self::IDENTIFIER, ':values'))
            ->setParameter('values', $ids);

        return $builder->getQuery()->getResult();
    }

    private function getRepository(): EntityRepository
    {
        /** @var EntityRepository $repository */
        $repository = $this->managerRegistry
            ->getManagerForClass($this->getIndex()->getClass())
            ->getRepository($this->getIndex()->getClass());

        return $repository;
    }

    private function sort(array $ids, array &$items)
    {
        $idPos = array_flip($ids);
        usort(
            $items,
            function ($a, $b) use ($idPos) {
                return
                    $idPos[$this->getIdentityValue($a)]
                    >
                    $idPos[$this->getIdentityValue($b)];
            }
        );
    }

    public function findPaginated($query = '', int $page = 1, int $limit = 10): Pagerfanta
    {
        $items = [];
        $ids = [];

        $result = $this->doFind($query, $page, $limit);
        $total = $result->getTotal();

        foreach ($result as $item) {
            $ids[] = $item->getId();
        }

        if (\count($ids) > 0) {
            $items = $this->hydrateItems($ids);
            $this->sort($ids, $items);
        }

        $pager = $this->createPager($items, $total);
        $pager->setMaxPerPage($limit);
        $pager->setCurrentPage($page);

        return $pager;
    }

    private function createPager(array $items, int $total): Pagerfanta
    {
        $adapter = new FixedAdapter($total, $items);

        return new Pagerfanta($adapter);
    }

    public function createObjectPager(): Pagerfanta
    {
        $class = $this->index->getClass();

        /** @var EntityRepository $repository */
        $repository = $this->managerRegistry
            ->getManagerForClass($class)
            ->getRepository($class);

        $queryBuilder = $repository->createQueryBuilder(IndexManager::ALIAS);

        $adapter = new QueryAdapter($queryBuilder);

        return new Pagerfanta($adapter);
    }

    private function collectData(ResultSet $resultSet): ResultSet
    {
        $time = (float) $resultSet->getResponse()->getTime();
        $response = $resultSet->getResponse()->getResponse();
        $request = $resultSet->getResponse()->getTransportInfo();
        $this->logger->logQuery($request, $response, $time);

        return $resultSet;
    }
}
