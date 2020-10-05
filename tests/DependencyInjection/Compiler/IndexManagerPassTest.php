<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Versh23\ManticoreBundle\DependencyInjection\Compiler\IndexManagerPass;
use Versh23\ManticoreBundle\IndexManagerRegistry;

class IndexManagerPassTest extends TestCase
{
    public function testAddIndexManagerToRegistry()
    {
        $container = $this->createMock(ContainerBuilder::class);
        $registryDef = $this->createMock(Definition::class);
        $registryDef->expects($this->exactly(2))
            ->method('addMethodCall')
            ->withConsecutive(
                ['addIndexManager', [new Reference('index_manager_1')]],
                ['addIndexManager', [new Reference('index_manager_2')]]
            );

        $container->expects($this->once())->method('hasDefinition')
            ->with(IndexManagerRegistry::class)->willReturn(true);
        $container->expects($this->once())->method('getDefinition')
            ->with(IndexManagerRegistry::class)->willReturn($registryDef);
        $container->expects($this->once())->method('findTaggedServiceIds')->willReturn(
            ['index_manager_1' => null, 'index_manager_2' => null]
        );

        $pass = new IndexManagerPass();
        $pass->process($container);
    }
}
