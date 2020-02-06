<?php


namespace Versh23\ManticoreBundle\Command;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
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

    private const LIMIT = 100;

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

        $io->block('Start populate index ' . $index);

        $class = $this->registry->getClassByIndex($index);
        $indexManager = $this->registry->getIndexManager($index);

        $indexManager->truncateIndex();

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->managerRegistry
            ->getManagerForClass($class)
            ->getRepository($class)
            ->createQueryBuilder(IndexManager::ALIAS);


        $adapter = new DoctrineORMAdapter($queryBuilder);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage(self::LIMIT);
        $pager->setCurrentPage(1);

        $lastPage = $pager->getNbPages();
        $page = $pager->getCurrentPage();

        $io->block(sprintf('Start with page = %s, limit = %s', $pager->getCurrentPage(), $pager->getMaxPerPage()));

        $progressBar = $io->createProgressBar($pager->getNbResults());
        $progressBar->start();

        do {
            $pager->setCurrentPage($page);
            $objects = $pager->getCurrentPageResults();

            if ($objects instanceof \Traversable) {
                $objects = iterator_to_array($objects);
            }

            $indexManager->bulkInsert($objects);

            $progressBar->advance(count($objects));

            $page++;
        } while ($page <= $lastPage);

        $progressBar->finish();

        $indexManager->flushIndex();


        return 0;
    }
}