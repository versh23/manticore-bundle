<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityRepository;
use Foolz\SphinxQL\Drivers\ResultSetInterface;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Adapter\FixedAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class IndexManager
{
    public const ALIAS = 'o';
    private const IDENTIFIER = 'id';

    private const ACTION_INSERT = 'insert';
    private const ACTION_UPDATE = 'update';
    private const ACTION_REPLACE = 'replace';

    private $connection;
    private $index;

    private $propertyAccessor = null;
    private $managerRegistry;

    public function __construct(Connection $connection, Index $index, ManagerRegistry $managerRegistry)
    {
        $this->connection = $connection;
        $this->index = $index;
        $this->managerRegistry = $managerRegistry;
    }

    public function truncateIndex(): ResultSetInterface
    {
        return $this->createHelper()->truncateRtIndex($this->getIndex()->getName())->execute();
    }

    public function createHelper(): Helper
    {
        return new Helper($this->connection);
    }

    public function flushIndex(): ResultSetInterface
    {
        return $this->createHelper()->flushRtIndex($this->getIndex()->getName())->execute();
    }

    public function replace($object): ResultSetInterface
    {
        return $this->createCRUDQuery($object, self::ACTION_REPLACE)->execute();
    }

    private function createCRUDQuery($object, string $action, ?SphinxQL $sphinxQL = null): SphinxQL
    {
        $index = $this->getIndex();

        $columns = [];
        $values = [];

        if (self::ACTION_UPDATE !== $action) {
            $columns = [self::IDENTIFIER];
            $values = [$this->getIdentityValue($object)];

            foreach ($index->getFields() as $name => $field) {
                $columns[] = $name;
                $values[] = $this->getValue($object, $field['property'], Index::ATTR_TYPE_STRING);
            }
        }

        foreach ($index->getAttributes() as $name => $attribute) {
            $columns[] = $name;
            $values[] = $this->getValue($object, $attribute['property'], $attribute['type']);
        }

        $prevQL = null;

        if (self::ACTION_UPDATE === $action && null !== $sphinxQL) {
            $prevQL = (clone $sphinxQL)->compile()->getCompiled();
        }

        if (!$sphinxQL) {
            $sphinxQL = $this->createQuery();

            switch ($action) {
                case self::ACTION_INSERT:
                    $sphinxQL = $sphinxQL->insert()->into($index->getName());
                    break;
                case self::ACTION_REPLACE:
                    $sphinxQL = $sphinxQL->replace()->into($index->getName());
                    break;
                case self::ACTION_UPDATE:
                    $sphinxQL = $sphinxQL->update($index->getName());
                    break;
            }
        }

        $sphinxQL->set(array_combine($columns, $values));

        if (self::ACTION_UPDATE === $action) {
            $sphinxQL
                ->resetWhere()
                ->where(self::IDENTIFIER, '=', $this->getIdentityValue($object));

            if (null !== $prevQL) {
                $currentQL = $sphinxQL->compile()->getCompiled();
                $sphinxQL->query($prevQL.';'.$currentQL);
            }
        }

        return $sphinxQL;
    }

    public function getIndex(): Index
    {
        return $this->index;
    }

    public function createObjectPager(): Pagerfanta
    {
        $class = $this->index->getClass();

        /** @var EntityRepository $repository */
        $repository = $this->managerRegistry
            ->getManagerForClass($class)
            ->getRepository($class);

        $queryBuilder = $repository->createQueryBuilder(IndexManager::ALIAS);

        $adapter = new DoctrineORMAdapter($queryBuilder);

        return new Pagerfanta($adapter);
    }

    private function getValue($object, string $property, string $type = Index::ATTR_TYPE_STRING)
    {
        $propertyAccessor = $this->getPropertyAccessor();
        $value = $propertyAccessor->getValue($object, $property);

        switch ($type) {
            case Index::ATTR_TYPE_FLOAT:
                return (float) $value;
            case Index::ATTR_TYPE_BOOL:
                return (bool) $value;
            case Index::ATTR_TYPE_INT:
            case Index::ATTR_TYPE_BIGINT:
                return (int) $value;
            case Index::ATTR_TYPE_TIMESTAMP:
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->getTimestamp();
                }

                return (int) $value;
            case Index::ATTR_TYPE_JSON:
                if (is_array($value)) {
                    $value = json_encode($value);
                }

                return (string) $value;
            case Index::ATTR_TYPE_MVA:
                return (array) $value;
            case Index::ATTR_TYPE_STRING:
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

    public function delete(array $ids): ?ResultSetInterface
    {
        if (!count($ids)) {
            return null;
        }

        $sq = $this->createQuery()
            ->delete()
            ->from($this->getIndex()->getName())
            ->where('id', 'in', $ids);

        return $sq->execute();
    }

    public function bulkReplace(array $objects): ?ResultSetInterface
    {
        return $this->bulkAction($objects, self::ACTION_REPLACE);
    }

    public function bulkUpdate(array $objects): ?ResultSetInterface
    {
        return $this->bulkAction($objects, self::ACTION_UPDATE);
    }

    private function bulkAction(array $objects, string $action): ?ResultSetInterface
    {
        if (!count($objects)) {
            return null;
        }

        $sq = null;

        foreach ($objects as $object) {
            $sq = $this->createCRUDQuery($object, $action, $sq);
        }

        return $sq->execute();
    }

    public function bulkInsert(array $objects): ?ResultSetInterface
    {
        return $this->bulkAction($objects, self::ACTION_INSERT);
    }

    public function insert($object): ResultSetInterface
    {
        return $this->createCRUDQuery($object, self::ACTION_INSERT)->execute();
    }

    public function update($object): ResultSetInterface
    {
        return $this->createCRUDQuery($object, self::ACTION_UPDATE)->execute();
    }

    public function find($query = '', int $page = 1, int $limit = 10): array
    {
        $resultData = $this->doFind($query, $page, $limit);

        return $resultData['items'];
    }

    private function getIdsResults(SphinxQL $query): array
    {
        $result = $query
            ->enqueue($this->createHelper()->showMeta())
            ->executeBatch();

        $rawItems = $result->getNext()->fetchAllAssoc();

        $ids = [];
        foreach ($rawItems as $item) {
            $ids[] = (int) $item['id'];
        }

        $meta = $result->getNext()->fetchAllAssoc();

        return [$ids, $this->parseTotal($meta)];
    }

    private function doFind($query = '', int $page = 1, int $limit = 10): array
    {
        $resultData['total'] = 0;
        $resultData['items'] = [];

        $page = max($page, 1);
        $offset = ($page - 1) * $limit;

        if (!$query instanceof SphinxQL) {
            $baseQuery = $this->createBaseQuery();

            if (is_string($query) && '' !== $query) {
                $baseQuery->match($this->getIndex()->getFieldsName(), $query);
            }
        } else {
            $baseQuery = $query;
        }

        $baseQuery->limit($offset, $limit);

        [$ids, $total] = $this->getIdsResults($baseQuery);

        $items = [];

        if (count($ids) > 0) {
            $items = $this->hydrateItems($ids);
            $this->sort($ids, $items);
        }

        $resultData['items'] = $items;
        $resultData['total'] = $total;

        return $resultData;
    }

    private function createBaseQuery(): SphinxQL
    {
        return $this->createQuery()
            ->select('id', 'WEIGHT() as w')
            ->from($this->getIndex()->getName())
            ->orderBy('w', 'DESC');
    }

    public function createQuery(): SphinxQL
    {
        return new SphinxQL($this->connection);
    }

    private function parseTotal(array $meta): int
    {
        $total = 0;

        foreach ($meta as $item) {
            if ('total_found' === $item['Variable_name']) {
                $total = (int) $item['Value'];
                break;
            }
        }

        return $total;
    }

    private function getRepository(): EntityRepository
    {
        /** @var EntityRepository $repository */
        $repository = $this->managerRegistry
            ->getManagerForClass($this->getIndex()->getClass())
            ->getRepository($this->getIndex()->getClass());

        return $repository;
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
        $resultData = $this->doFind($query, $page, $limit);

        $pager = $this->createPager($resultData['items'], $resultData['total']);
        $pager->setMaxPerPage($limit);
        $pager->setCurrentPage($page);

        return $pager;
    }

    private function createPager(array $items, int $total): Pagerfanta
    {
        $adapter = new FixedAdapter($total, $items);

        return new Pagerfanta($adapter);
    }

    public function getIdentityValue($object)
    {
        return (int) $this->getPropertyAccessor()->getValue($object, self::IDENTIFIER);
    }

    public function isIndexable($object): bool
    {
        return get_class($object) === $this->getIndex()->getClass();
    }
}
