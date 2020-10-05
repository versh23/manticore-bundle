<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Versh23\ManticoreBundle\DependencyInjection\Versh23ManticoreExtension;

class Versh23ManticoreExtensionTest extends TestCase
{
    public function testRegisterEntity()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);

        $entity = new class() {
        };

        $config = [
            'versh23_manticore' => [
                'client' => [],
                'indexes' => [
                    'index1' => [
                        'class' => get_class($entity),
                        'fields' => [],
                    ],
                ],
            ],
        ];
        $extension = new Versh23ManticoreExtension();
        $extension->load($config, $container);

        $this->assertTrue($container->hasDefinition('manticore.index.index1'));
        $this->assertTrue($container->hasDefinition('manticore.index_manager.index1'));
        $this->assertTrue($container->getDefinition('manticore.index_manager.index1')
            ->hasTag(Versh23ManticoreExtension::INDEX_MANAGER_TAG));

        $this->assertTrue($container->hasDefinition('manticore.listener.index1'));
        $this->assertSame([
            'doctrine.event_listener' => [
                ['event' => 'postPersist'],
                ['event' => 'postUpdate'],
                ['event' => 'preRemove'],
                ['event' => 'postFlush'],
            ],
        ], $container->getDefinition('manticore.listener.index1')
            ->getTags());
    }
}
