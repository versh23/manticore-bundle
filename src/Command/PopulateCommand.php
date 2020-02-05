<?php


namespace Versh23\ManticoreBundle\Command;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Versh23\ManticoreBundle\IndexManager;
use Versh23\ManticoreBundle\IndexManagerRegistry;

class PopulateCommand extends Command
{
    protected static $defaultName = 'manticore:index:populate';

    private $registry;
    private $managerRegistry;

    public function __construct(IndexManagerRegistry $registry,  ManagerRegistry $managerRegistry)
    {
        parent::__construct();
        $this->registry = $registry;
        $this->managerRegistry = $managerRegistry;
    }

    protected function configure()
    {
        $this
            ->setDescription('Populate manticore index')
            ->addArgument('index', InputArgument::REQUIRED, 'Index name need to populate')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $index = $input->getArgument('index');

        $class = $this->registry->getClassByIndex($index);

        $manager = $this->registry->getIndexManager($index);

        $manager->truncateIndex();


        $page = 1;
        $limit = 2;

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->managerRegistry
            ->getManagerForClass($class)
            ->getRepository($class)
            ->createQueryBuilder(IndexManager::ALIAS);

        $queryBuilder->setMaxResults($limit);
        $queryBuilder->setFirstResult(0);
        $paginator = new Paginator($queryBuilder);

        $lastPage = (int) ceil($paginator->count() / $limit);

        while ($page <= $lastPage) {

            $offset = ($page - 1) * $limit;

            $queryBuilder->setMaxResults($limit);
            $queryBuilder->setFirstResult($offset);

            $paginator = new Paginator($queryBuilder);

            //insert
            $objects = iterator_to_array($paginator->getIterator());

            $manager->insertMany($objects);

            $page++;

        }


        $manager->flushIndex();


        return 0;
    }
}