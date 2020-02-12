<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Versh23\ManticoreBundle\DependencyInjection\Compiler\IndexManagerPass;
use Versh23\ManticoreBundle\IndexManager;
use Versh23\ManticoreBundle\IndexManagerRegistry;

class IndexManagerPassTest extends TestCase
{
    public function testRegisterTaggedIndexManagers()
    {
        $container = new ContainerBuilder();
        $pass = new IndexManagerPass();

        $registry = new Definition(IndexManagerRegistry::class);
        $container->setDefinition('manticore.index_manager_registry', $registry);

        $def1 = new Definition(IndexManager::class);
        $def1->addTag('manticore.index_manager');
        $container->setDefinition('manticore.index_manager.1', $def1);

        $def2 = new Definition(IndexManager::class);
        $def2->addTag('manticore.index_manager');
        $container->setDefinition('manticore.index_manager.2', $def2);

        $pass->process($container);

        $calls = $registry->getMethodCalls();

        $this->assertCount(2, $calls);

        $this->assertSame('manticore.index_manager.1', (string) $calls[0][1][0]);
        $this->assertSame('manticore.index_manager.2', (string) $calls[1][1][0]);
    }
}
