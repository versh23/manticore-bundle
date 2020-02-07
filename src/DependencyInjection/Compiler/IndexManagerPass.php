<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class IndexManagerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('manticore.index_manager_registry')) {
            return;
        }

        $def = $container->getDefinition('manticore.index_manager_registry');

        foreach ($container->findTaggedServiceIds('manticore.index_manager') as $serviceId => $tags) {
            $def->addMethodCall('addIndexManager', [new Reference($serviceId)]);
        }
    }
}
