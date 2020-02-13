<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\DependencyInjection;

use Doctrine\ORM\Events;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class Versh23ManticoreExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        //TODO only PDO ?
        $connectionClass = \Foolz\SphinxQL\Drivers\Pdo\Connection::class;
        $connectionId = 'manticore.connection';
        $connectionRef = new Definition($connectionClass);
        $connectionRef->addMethodCall('setParams', [
            [
                'host' => $config['host'],
                'port' => $config['port'],
            ],
        ]);
        $container->setDefinition($connectionId, $connectionRef);

        foreach ($config['indexes'] as $name => $indexConfig) {
            $indexId = sprintf('manticore.index.%s', $name);
            $indexDef = new ChildDefinition('manticore.index_prototype');
            $indexDef->replaceArgument(0, $name);
            $indexDef->replaceArgument(1, $indexConfig['class']);
            $indexDef->replaceArgument(2, $indexConfig['fields']);
            $indexDef->replaceArgument(3, $indexConfig['attributes']);
            $container->setDefinition($indexId, $indexDef);

            $indexManagerId = sprintf('manticore.index_manager.%s', $name);
            $indexManagerDef = new ChildDefinition('manticore.index_manager_prototype');
            $indexManagerDef->replaceArgument(0, new Reference($connectionId));
            $indexManagerDef->replaceArgument(1, new Reference($indexId));
            $indexManagerDef->addTag('manticore.index_manager');
            $container->setDefinition($indexManagerId, $indexManagerDef);

            $listenerId = sprintf('manticore.listener.%s', $name);
            $listenerDef = new ChildDefinition('manticore.listener_prototype');
            $listenerDef->replaceArgument(0, new Reference($indexManagerId));
            $listenerDef->addTag('doctrine.orm.entity_listener', ['event' => Events::postPersist]);
            $listenerDef->addTag('doctrine.orm.entity_listener', ['event' => Events::postUpdate]);
            $listenerDef->addTag('doctrine.orm.entity_listener', ['event' => Events::preRemove]);
            $listenerDef->addTag('doctrine.orm.entity_listener', ['event' => Events::postFlush]);
            $container->setDefinition($listenerId, $listenerDef);
        }
    }
}
