<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Manticoresearch\Client;
use Symfony\Component\DependencyInjection\Reference;
use Versh23\ManticoreBundle\Command\PopulateCommand;
use Versh23\ManticoreBundle\Index;
use Versh23\ManticoreBundle\IndexManager;
use Versh23\ManticoreBundle\IndexManagerRegistry;
use Versh23\ManticoreBundle\Listener;
use Versh23\ManticoreBundle\Logger;
use Versh23\ManticoreBundle\ManticoreDataCollector;

return static function (ContainerConfigurator $container) {
    $services = $container->services()
        ->defaults()->private();

    $services

        ->set(Client::class)
            ->arg(0, [])
            ->arg(1, new Reference(Logger::class))

        ->set(Logger::class)
            ->arg(0, (new ReferenceConfigurator('logger'))->nullOnInvalid())
            ->arg(1, '%kernel.debug%')
            ->tag('monolog.logger', ['channel' => 'manticore'])

        ->set(PopulateCommand::class)
            ->arg(0, new Reference(IndexManagerRegistry::class))
            ->arg(1, new Reference('doctrine'))
            ->tag('console.command')

        ->set(IndexManagerRegistry::class)

        ->set('manticore.index_prototype', Index::class)->abstract()
            ->arg(0, null)
            ->arg(1, null)
            ->arg(2, null)
            ->arg(3, null)

        ->set('manticore.index_manager_prototype', IndexManager::class)->abstract()
            ->arg(0, new Reference(Client::class))
            ->arg(1, null)
            ->arg(2, new Reference('doctrine'))

        ->set('manticore.listener_prototype', Listener::class)->abstract()
            ->arg(0, null)

    // TODO rework data collector
//        ->set(ManticoreDataCollector::class)
//            ->arg(0, new Reference(Logger::class))
//            ->tag('data_collector', [
//                'template' => '@Versh23Manticore/data_collector/template.html.twig',
//                'id' => 'manticore.data_collector'
//            ])
    ;
};
