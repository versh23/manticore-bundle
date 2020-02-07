<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Versh23\ManticoreBundle\IndexManagerRegistry;

class IndexConfigCommand extends Command
{
    protected static $defaultName = 'manticore:index:config';

    private $registry;

    public function __construct(IndexManagerRegistry $registry)
    {
        parent::__construct();
        $this->registry = $registry;
    }

    protected function configure()
    {
        $this
            ->setDescription('Render config sample')
            ->addArgument('index', InputArgument::REQUIRED, 'Index name need to config')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        //TODO all indexes
        $indexName = $input->getArgument('index');

        $indexManager = $this->registry->getIndexManager($indexName);

        $index = $indexManager->getIndex();

        $fieldItem = '';
        foreach ($index->getFields() as $name => $field) {
            $fieldItem = $fieldItem.PHP_EOL.<<<EOT
            rt_field = $name
EOT;
        }

        $articleItem = '';

        foreach ($index->getAttributes() as $name => $attribute) {
            $articleItem = $articleItem.PHP_EOL.<<<EOT
            rt_attr_{$attribute['type']} = $name
EOT;
        }

        $configItem = <<<EOT
        index {$index->getName()} {
            # other settings
            
            type = rt
            
            #fields$fieldItem
            
            #attributes$articleItem
        }
EOT;

        $io->newLine(5);
        $io->writeln($configItem);
        $io->newLine(5);

        return 0;
    }
}
