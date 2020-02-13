<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;
use Versh23\ManticoreBundle\DependencyInjection\Versh23ManticoreExtension;
use Versh23\ManticoreBundle\Tests\Entity\SimpleEntity;

class Versh23ManticoreExtensionTest extends TestCase
{
    public function testRegister()
    {
        $config = Yaml::parse(file_get_contents(__DIR__.'/config.yaml'));

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.debug', true);

        $extension = new Versh23ManticoreExtension();

        $extension->load($config, $containerBuilder);

        $def = $containerBuilder->getDefinition('manticore.index_manager.simple_entity');
        $prototypeDef = $containerBuilder->getDefinition('manticore.index_manager_prototype');
        $this->assertSame('doctrine', (string) $prototypeDef->getArgument(2));

        $this->assertTrue($def->hasTag('manticore.index_manager'));
        $this->assertSame('manticore.index_manager_prototype', $def->getParent());

        $this->assertSame('manticore.connection', (string) $def->getArgument(0));
        $this->assertSame('manticore.index.simple_entity', (string) $def->getArgument(1));

        $indexDef = $containerBuilder->getDefinition('manticore.index.simple_entity');
        $this->assertSame('simple_entity', $indexDef->getArgument(0));
        $this->assertSame(SimpleEntity::class, $indexDef->getArgument(1));
        $this->assertSame(
            [
                'description' => ['property' => 'description'],
                'name' => ['property' => 'name'],
            ],
            $indexDef->getArgument(2));
        $this->assertSame(
            [
                'free' => ['type' => 'bool', 'property' => 'free'],
                'status' => ['property' => 'status', 'type' => 'string'],
            ], $indexDef->getArgument(3));

        $listenerDef = $containerBuilder->getDefinition('manticore.listener.simple_entity');
        $this->assertSame('manticore.index_manager.simple_entity', (string) $listenerDef->getArgument(0));
        $tag = $listenerDef->getTag('doctrine.orm.entity_listener');
        $this->assertSame([
            ['event' => 'postPersist'],
            ['event' => 'postUpdate'],
            ['event' => 'preRemove'],
            ['event' => 'postFlush'],
        ], $tag);

        $connectionDef = $containerBuilder->getDefinition('manticore.connection');
        $calls = $connectionDef->getMethodCalls();
        $setParams = $calls[0];
        $this->assertSame('manticore', $setParams[1][0]['host']);
        $this->assertSame(9306, $setParams[1][0]['port']);
    }
}
