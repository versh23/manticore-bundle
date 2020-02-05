<?php


namespace Versh23\ManticoreBundle;


use App\Entity\Article;
use Doctrine\ORM\EntityRepository;
use Foolz\SphinxQL\Drivers\ConnectionBase;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;
use Pagerfanta\Adapter\FixedAdapter;
use Pagerfanta\Pagerfanta;

class IndexManager
{

    public const ALIAS = 'o';
    private const IDENTIFIER = 'id';

    private $connection;
    private $index;

    public function __construct(ConnectionBase $connection, Index $index)
    {
        $this->connection = $connection;
        $this->index = $index;
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

    public function replace(): void
    {

    }

    public function insert(): void
    {

    }

    public function update(): void
    {

    }

    public function findPaginated(string $query = '', int $limit = 10, int $page = 1): Pagerfanta
    {
        $page = max($page, 1);

        $offset = ($page - 1) * $limit;

        $baseQuery = $this->createQuery()
            ->select('id', 'WEIGHT() as w')
            ->from($this->index->getName())
            ->orderBy('w', 'DESC')
            ->limit($offset, $limit);

        if ($query) {
            $baseQuery->match($this->index->getFields(), $query);
        }

        $result = $baseQuery
            ->enqueue($this->createHelper()->showMeta())
            ->executeBatch()
        ;

        $rawItems = $result->getNext()->fetchAllAssoc();
        $meta = $result->getNext()->fetchAllAssoc();
        $total = 0;

        foreach ($meta as $item) {
            if ('total_found' === $item['Variable_name']) {
                $total = (int) $item['Value'];
                break;
            }
        }

        $ids = [];
        foreach ($rawItems as $item) {
            $ids[] = (int) $item['id'];
        }

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
            function ($a, $b) use ($idPos) {
                //TODO property accessor
                return $idPos[$a->getId()] > $idPos[$b->getId()];
            }
        );

        $adapter = new FixedAdapter($total, $items);

        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage($limit);
        $pagerfanta->setCurrentPage($page);

        return $pagerfanta;
    }

    public function getIndex(): Index
    {
        return $this->index;
    }

    public function insertMany(array $objects): void
    {
//        dd($objects);
    }

}