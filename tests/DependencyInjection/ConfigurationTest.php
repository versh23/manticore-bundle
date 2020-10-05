<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Versh23\ManticoreBundle\DependencyInjection\Configuration;

class ConfigurationTest extends TestCase
{
    private $processor;

    protected function setUp(): void
    {
        $this->processor = new Processor();
    }

    public function testShortSyntax()
    {
        $config = $this->getConfigs([
            'indexes' => [
                'index' => [
                    'fields' => [
                        'field1' => null,
                        'field2' => 'integer',
                    ],
                ],
            ],
        ]);

        $this->assertSame([
            'indexes' => [
                'index' => [
                    'fields' => [
                        'field1' => [
                            'type' => 'text',
                            'property' => 'field1',
                            'options' => [],
                        ],
                        'field2' => [
                            'type' => 'integer',
                            'property' => 'field2',
                            'options' => [],
                        ],
                    ],
                    'options' => [],
                ],
            ],
        ], $config);
    }

    public function testFullSyntax()
    {
        $config = $this->getConfigs([
            'indexes' => [
                'index' => [
                    'fields' => [
                        'field1' => [
                            'type' => 'text',
                            'property' => 'fullText',
                            'options' => ['indexed'],
                        ],
                    ],
                    'options' => [
                        'morphology' => 'stem_ru',
                    ],
                ],
            ],
        ]);

        $this->assertSame([
            'indexes' => [
                'index' => [
                    'fields' => [
                        'field1' => [
                            'type' => 'text',
                            'property' => 'fullText',
                            'options' => ['indexed'],
                        ],
                    ],
                    'options' => [
                        'morphology' => 'stem_ru',
                    ],
                ],
            ],
        ], $config);
    }

    public function testInvalidType()
    {
        $this->expectExceptionMessage('Type is not valid. Must be [text, integer, float, multi, multi64, bool, json, string, timestamp]');
        $this->getConfigs([
            'indexes' => [
                'index' => [
                    'fields' => [
                        'field1' => 'jsonb',
                    ],
                ],
            ],
        ]);
    }

    private function getConfigs(array $configArray)
    {
        $configuration = new Configuration();

        return $this->processor->processConfiguration($configuration, [$configArray]);
    }
}
