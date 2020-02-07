<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle;

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

    public function __construct(ConnectionBase $connection, Index $index)
    {
        $this->connection = $connection;
        $this->index = $index;
    }

    private function getPropertyAccessor(): PropertyAccessor
    {
        if (null === $this->propertyAccessor) {
            $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        return $this->propertyAccessor;
    }

    public function createQuery(): SphinxQL
    {
        return new SphinxQL($this->connection);
    }

    public function createHelper(): Helper
    {
        return new Helper($this->connection);
    }

    public function truncateIndex(): void
    {
        $this->createHelper()->truncateRtIndex($this->index->getName())->execute();
    }

    public function flushIndex(): void
    {
        $this->createHelper()->flushRtIndex($this->index->getName())->execute();
    }

    public function replace($object): void
    {
        $this->createInsertReplaceQuery($object, false)->execute();
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

//    public function findPaginated(string $query = '', int $limit = 10, int $page = 1): Pagerfanta
//    {
//        $page = max($page, 1);
//
//        $offset = ($page - 1) * $limit;
//
//        $baseQuery = $this->createQuery()
//            ->select('id', 'WEIGHT() as w')
//            ->from($this->index->getName())
//            ->orderBy('w', 'DESC')
//            ->limit($offset, $limit);
//
//        if ($query) {
//            $baseQuery->match($this->index->getFields(), $query);
//        }
//
//        $result = $baseQuery
//            ->enqueue($this->createHelper()->showMeta())
//            ->executeBatch()
//        ;
//
//        $rawItems = $result->getNext()->fetchAllAssoc();
//        $meta = $result->getNext()->fetchAllAssoc();
//        $total = 0;
//
//        foreach ($meta as $item) {
//            if ('total_found' === $item['Variable_name']) {
//                $total = (int) $item['Value'];
//                break;
//            }
//        }
//
//        $ids = [];
//        foreach ($rawItems as $item) {
//            $ids[] = (int) $item['id'];
//        }
//
//        /** @var EntityRepository $repository */
//        $repository = $this->managerRegistry
//            ->getManagerForClass($this->index->getClass())
//            ->getRepository($this->index->getClass());
//
//        $builder = $repository
//            //TODO custom builder
//            ->createQueryBuilder(self::ALIAS);
//
//        $builder->andWhere($builder->expr()->in(self::ALIAS.'.'.self::IDENTIFIER, ':values'))
//            ->setParameter('values', $ids);
//        $items = $builder->getQuery()->getResult();
//
//        $idPos = array_flip($ids);
//        usort(
//            $items,
//            function ($a, $b) use ($idPos) {
//                //TODO property accessor
//                return $idPos[$a->getId()] > $idPos[$b->getId()];
//            }
//        );
//
//        $adapter = new FixedAdapter($total, $items);
//
//        $pagerfanta = new Pagerfanta($adapter);
//        $pagerfanta->setMaxPerPage($limit);
//        $pagerfanta->setCurrentPage($page);
//
//        return $pagerfanta;
//    }

    public function getIndex(): Index
    {
        return $this->index;
    }
}
