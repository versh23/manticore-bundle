<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Command;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityRepository;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
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

    private $indexManagerRegistry;
    private $managerRegistry;

    public function __construct(IndexManagerRegistry $indexManagerRegistry, ManagerRegistry $managerRegistry)
    {
        parent::__construct();
        $this->indexManagerRegistry = $indexManagerRegistry;
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

        //TODO all indexes
        $indexName = $input->getArgument('index');

        //TODO configure
        $limit = self::LIMIT;
        $page = 1;

        $io->block('Start populate index '.$indexName);

        $class = $this->indexManagerRegistry->getClassByIndex($indexName);
        $indexManager = $this->indexManagerRegistry->getIndexManager($indexName);

        $indexManager->truncateIndex();

        /** @var EntityRepository $repository */
        $repository = $this->managerRegistry
            ->getManagerForClass($class)
            ->getRepository($class);

        $queryBuilder = $repository->createQueryBuilder(IndexManager::ALIAS);

        $adapter = new DoctrineORMAdapter($queryBuilder);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($limit);
        $pager->setCurrentPage($page);

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

            ++$page;
        } while ($page <= $lastPage);

        $progressBar->finish();

        $indexManager->flushIndex();

        $io->newLine(3);
        $io->success('Complete');

        return 0;
    }
}
