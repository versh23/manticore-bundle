<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityRepository;
use Foolz\SphinxQL\Drivers\ConnectionBase;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;
use Pagerfanta\Adapter\FixedAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class IndexManager
{
    public const ALIAS = 'o';
    private const IDENTIFIER = 'id';

    private $connection;
    private $index;

    private $propertyAccessor = null;
    private $managerRegistry;

    public function __construct(ConnectionBase $connection, Index $index, ManagerRegistry $managerRegistry)
    {
        $this->connection = $connection;
        $this->index = $index;
        $this->managerRegistry = $managerRegistry;
    }

    public function truncateIndex(): void
    {
        $this->createHelper()->truncateRtIndex($this->index->getName())->execute();
    }

    public function createHelper(): Helper
    {
        return new Helper($this->connection);
    }

    public function flushIndex(): void
    {
        $this->createHelper()->flushRtIndex($this->index->getName())->execute();
    }

    public function replace($object): void
    {
        $this->createInsertReplaceQuery($object, false)->execute();
    }

    private function createInsertReplaceQuery($object, bool $insert = true, ?SphinxQL $sphinxQL = null): SphinxQL
    {
        $index = $this->getIndex();

        $columns = [self::IDENTIFIER];
        $values = [$this->getValue($object, self::IDENTIFIER, Index::ATTR_TYPE_BIGINT)];

        foreach ($index->getFields() as $name => $field) {
            $columns[] = $name;
            $values[] = $this->getValue($object, $field['property'], Index::ATTR_TYPE_STRING);
        }

        foreach ($index->getAttributes() as $name => $attribute) {
            $columns[] = $name;
            $values[] = $this->getValue($object, $attribute['property'], $attribute['type']);
        }

        if (!$sphinxQL) {
            $sphinxQL = (new SphinxQL($this->connection));
            $sphinxQL = $insert ? $sphinxQL->insert()->into($index->getName()) : $sphinxQL->replace()->into($index->getName());
        }

        $sphinxQL->columns($columns);
        $sphinxQL->values($values);

        return $sphinxQL;
    }

    public function getIndex(): Index
    {
        return $this->index;
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
                throw new \Exception('Not implemented yet');
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

    public function bulkInsert(array $objects): void
    {
        if (!count($objects)) {
            return;
        }

        $sq = null;

        foreach ($objects as $object) {
            $sq = $this->createInsertReplaceQuery($object, true, $sq);
        }

        $sq->execute();
    }

    public function insert($object): void
    {
        $this->createInsertReplaceQuery($object, true)->execute();
    }

    public function update(): void
    {
        //TODO check update field
        //All attributes types (int, bigint, float, strings, MVA, JSON) can be dynamically updated.

        throw new \Exception('Not implemented yet');
    }

    public function find(string $query = '', int $page = 1, int $limit = 10): array
    {
        $resultData = $this->doFind($query, $page, $limit);

        return $resultData['items'];
    }

    private function doFind(string $query = '', int $limit = 10, int $page = 1): array
    {
        $resultData['total'] = 0;
        $resultData['items'] = [];

        $page = max($page, 1);
        $offset = ($page - 1) * $limit;

        $baseQuery = $this->createBaseQuery();
        $baseQuery->limit($offset, $limit);

        if ($query) {
            $baseQuery->match($this->index->getFields(), $query);
        }

        $result = $baseQuery
            ->enqueue($this->createHelper()->showMeta())
            ->executeBatch();

        $rawItems = $result->getNext()->fetchAllAssoc();
        $ids = [];
        foreach ($rawItems as $item) {
            $ids[] = (int) $item['id'];
        }

        $meta = $result->getNext()->fetchAllAssoc();

        $resultData['total'] = $this->parseTotal($meta);
        $resultData['items'] = $this->hydrateItems($ids);

        return $resultData;
    }

    private function createBaseQuery(): SphinxQL
    {
        return $this->createQuery()
            ->select('id', 'WEIGHT() as w')
            ->from($this->index->getName())
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

    private function hydrateItems(array $ids): array
    {
        $propertyAccessor = $this->getPropertyAccessor();

        /** @var EntityRepository $repository */
        $repository = $this->managerRegistry
            ->getManagerForClass($this->index->getClass())
            ->getRepository($this->index->getClass());

        $builder = $repository
            //TODO custom builder
            ->createQueryBuilder(self::ALIAS);

        $builder->andWhere($builder->expr()->in(self::ALIAS.'.'.self::IDENTIFIER, ':values'))
            ->setParameter('values', $ids);
        $items = $builder->getQuery()->getResult();

        $idPos = array_flip($ids);
        usort(
            $items,
            function ($a, $b) use ($idPos, $propertyAccessor) {
                return
                    $idPos[$propertyAccessor->getValue($a, self::IDENTIFIER)]
                    >
                    $idPos[$propertyAccessor->getValue($b, self::IDENTIFIER)];
            }
        );

        return $items;
    }

    public function findPaginated(string $query = '', int $page = 1, int $limit = 10): Pagerfanta
    {
        $resultData = $this->doFind($query, $page, $limit);

        $pagerfanta = $this->createPagerfanta($resultData['items'], $resultData['total']);
        $pagerfanta->setMaxPerPage($limit);
        $pagerfanta->setCurrentPage($page);

        return $pagerfanta;
    }

    private function createPagerfanta(array $items, int $total): Pagerfanta
    {
        $adapter = new FixedAdapter($total, $items);

        return new Pagerfanta($adapter);
    }
}
