<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Versh23\ManticoreBundle\IndexManagerRegistry;

class PopulateCommand extends Command
{
    protected static $defaultName = 'manticore:index:populate';

    private $indexManagerRegistry;
    private $managerRegistry;

    public function __construct(IndexManagerRegistry $indexManagerRegistry, Registry $managerRegistry)
    {
        parent::__construct();
        $this->indexManagerRegistry = $indexManagerRegistry;
        $this->managerRegistry = $managerRegistry;
    }

    protected function configure()
    {
        $this
            ->setDescription('Populate manticore index')
            ->addArgument('index', InputArgument::OPTIONAL, 'Index name need to config')

            ->addOption('page', null, InputOption::VALUE_REQUIRED, 'Start page', 1)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'The pager\'s page size', 100)
            ->addOption('recreate', null, InputOption::VALUE_REQUIRED, 'Recreate index before populate', false)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $indexName = $input->getArgument('index');
        $recreate = (bool) $input->getOption('recreate');

        $indexManagers = $indexName ? [$this->indexManagerRegistry->getIndexManager($indexName)] : $this->indexManagerRegistry->getAllIndexManagers();
        foreach ($indexManagers as $indexManager) {
            $limit = (int) $input->getOption('limit');
            $page = (int) $input->getOption('page');

            $indexName = $indexManager->getIndex()->getName();

            $io->block('Start populate index '.$indexName);

            if ($recreate) {
                $indexManager->createIndex(true);
            }

            $indexManager->truncateIndex();

            $pager = $indexManager->createObjectPager();
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

            $indexManager->flush();

            $io->newLine(3);
        }

        $io->success('Complete');

        return 0;
    }
}
