<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Versh23\ManticoreBundle\Command\IndexConfigCommand;
use Versh23\ManticoreBundle\Index;
use Versh23\ManticoreBundle\IndexManager;
use Versh23\ManticoreBundle\IndexManagerRegistry;

class IndexConfigCommandTest extends TestCase
{
    private function createIndexManager(string $name)
    {
        $index = $this->createMock(Index::class);
        $index->expects($this->atLeastOnce())->method('getName')->willReturn($name);
        $index->method('getFields')
            ->willReturn(
                [
                    'field1' => 'field1',
                    'field2' => 'field2',
                ]
            )
        ;
        $index->method('getAttributes')
            ->willReturn(
                [
                    'attr1' => [
                        'type' => 'string',
                    ],
                    'attr2' => [
                        'type' => 'timestamp',
                    ],
                ]
            )
        ;

        $indexManager = $this->createMock(IndexManager::class);
        $indexManager->expects($this->atLeastOnce())->method('getIndex')->willReturn($index);

        return $indexManager;
    }

    public function testExecuteAllIndexes()
    {
        $indexManagerRegistry = new IndexManagerRegistry();
        $indexManagerRegistry->addIndexManager($this->createIndexManager('test_index'));
        $indexManagerRegistry->addIndexManager($this->createIndexManager('test_index_second'));

        $command = new IndexConfigCommand($indexManagerRegistry);

        $output = new BufferedOutput();
        $command->run(new ArrayInput([]), $output);

        $config = <<<EOT
        index test_index {
            # other settings
            
            type = rt
            
            #fields
            rt_field = field1
            rt_field = field2
            
            #attributes
            rt_attr_string = attr1
            rt_attr_timestamp = attr2
        }

        index test_index_second {
            # other settings
            
            type = rt
            
            #fields
            rt_field = field1
            rt_field = field2
            
            #attributes
            rt_attr_string = attr1
            rt_attr_timestamp = attr2
        }
EOT;

        $this->assertStringContainsStringIgnoringCase(self::normalize($config), self::normalize($output->fetch()));
    }

    private static function normalize(string $string): string
    {
        return preg_replace('/\n{2,}/', '', $string);
    }

    public function testExecuteWithIndexName()
    {
        $indexManagerRegistry = new IndexManagerRegistry();
        $indexManagerRegistry->addIndexManager($this->createIndexManager('test_index'));
        $indexManagerRegistry->addIndexManager($this->createIndexManager('test_index_2'));

        $command = new IndexConfigCommand($indexManagerRegistry);
        $output = new BufferedOutput();

        $command->run(new ArrayInput([
            'index' => 'test_index',
        ]), $output);

        $config = <<<EOT
        index test_index {
            # other settings
            
            type = rt
            
            #fields
            rt_field = field1
            rt_field = field2
            
            #attributes
            rt_attr_string = attr1
            rt_attr_timestamp = attr2
        }
EOT;

        $this->assertStringContainsStringIgnoringCase(self::normalize($config), self::normalize($output->fetch()));
    }
}
