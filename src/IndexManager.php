<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityRepository;
use Manticoresearch\Client;
use Manticoresearch\Index;
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

    private $indexConfiguration;

    private $propertyAccessor = null;
    private $managerRegistry;
    private $client;
    private $logger;

    public function __construct(Client $client, IndexConfiguration $indexConfiguration, Registry $managerRegistry, Logger $logger)
    {
        $this->indexConfiguration = $indexConfiguration;
        $this->managerRegistry = $managerRegistry;
        $this->client = $client;
        $this->logger = $logger;
    }

    public function isIndexable($object): bool
    {
        return get_class($object) === $this->indexConfiguration->getClass();
    }

    public function createIndex(bool $recreate = false): void
    {
        $index = $this->getIndex();

        if ($recreate) {
            $index->drop(true);
        }

        $fields = [];
        foreach ($this->indexConfiguration->getFields() as $fieldName => $field) {
            $fields[$fieldName] = [
                'type' => $field['type'],
                'options' => $field['options'],
            ];
        }
        $settings = $this->indexConfiguration->getOptions();

        $index->create($fields, $settings);
    }

    public function getIndex(): Index
    {
        return $this->client->index($this->indexConfiguration->getName());
    }

    public function truncateIndex(): void
    {
        $this->getIndex()->truncate();
    }

    public function flush(): void
    {
        $this->getIndex()->flush();
    }

    public function delete($ids): void
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        if (!count($ids)) {
            return;
        }

        $index = $this->getIndex();
        foreach ($ids as $id) {
            $index->deleteDocument($id);
        }
    }

    public function bulkReplace(array $objects): void
    {
        $index = $this->getIndex();
        $documents = [];

        foreach ($objects as $object) {
            $documents[] = $this->createDocument($object);
        }

        $index->replaceDocuments($documents);
    }

    private function createDocument($object): array
    {
        $document = [];
        foreach ($this->indexConfiguration->getFields() as $name => $field) {
            $document[$name] = $this->getValue($object, $field['property'], $field['type']);
        }
        $document['id'] = $this->getIdentityValue($object);

        return $document;
    }

    // TODO object instead array

    private function getValue($object, string $property, string $type = IndexConfiguration::TYPE_TEXT)
    {
        $propertyAccessor = $this->getPropertyAccessor();
        $value = $propertyAccessor->getValue($object, $property);

        switch ($type) {
            case IndexConfiguration::TYPE_FLOAT:
                return (float) $value;
            case IndexConfiguration::TYPE_BOOL:
                return (bool) $value;
            case IndexConfiguration::TYPE_INTEGER:
                return (int) $value;
            case IndexConfiguration::TYPE_TIMESTAMP:
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->getTimestamp();
                }

                return (int) $value;
            case IndexConfiguration::TYPE_JSON:
                if (is_array($value)) {
                    $value = json_encode($value);
                }

                return (string) $value;
            case IndexConfiguration::TYPE_MULTI:
            case IndexConfiguration::TYPE_MULTI64:
                return (array) $value;
            case IndexConfiguration::TYPE_TEXT:
            case IndexConfiguration::TYPE_STRING:
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
        $index = $this->getIndex();
        $document = $this->createDocument($object);
        $id = $document['id'];
        unset($document['id']);

        $index->replaceDocument($document, $id);
    }

    public function insert($object): void
    {
        $index = $this->getIndex();
        $document = $this->createDocument($object);
        $id = $document['id'];
        unset($document['id']);

        $index->addDocument($document, $id);
    }

    public function bulkInsert(array $objects): void
    {
        $index = $this->getIndex();
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
        $index = $this->getIndex();
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
            ->getManagerForClass($this->indexConfiguration->getClass())
            ->getRepository($this->indexConfiguration->getClass());

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
        $class = $this->indexConfiguration->getClass();

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
