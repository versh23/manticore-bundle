<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Versh23\ManticoreBundle\DependencyInjection\Versh23ManticoreExtension;
use Versh23\ManticoreBundle\IndexManagerRegistry;

class IndexManagerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(IndexManagerRegistry::class)) {
            return;
        }

        $def = $container->getDefinition(IndexManagerRegistry::class);

        foreach ($container->findTaggedServiceIds(Versh23ManticoreExtension::INDEX_MANAGER_TAG) as $serviceId => $tags) {
            $def->addMethodCall('addIndexManager', [new Reference($serviceId)]);
        }
    }
}
