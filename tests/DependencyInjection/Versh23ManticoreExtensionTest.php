<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\Tests\DependencyInjection;

use Foolz\SphinxQL\Drivers\Pdo\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;
use Versh23\ManticoreBundle\DependencyInjection\Versh23ManticoreExtension;

class Versh23ManticoreExtensionTest extends TestCase
{
    public function testRegister()
    {
        $config = Yaml::parse(file_get_contents(__DIR__.'/config.yaml'));

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.debug', true);

        $extension = new Versh23ManticoreExtension();

        $extension->load($config, $containerBuilder);

        $this->assertTrue($containerBuilder->hasDefinition('manticore.index_manager.simple_entity'));

        $def = $containerBuilder->getDefinition('manticore.index_manager.simple_entity');

        $this->assertTrue($def->hasTag('manticore.index_manager'));
        $this->assertSame('manticore.index_manager_prototype', $def->getParent());

        $this->assertSame('manticore.connection', (string) $def->getArgument(0));
        $this->assertSame('manticore.index.simple_entity', (string) $def->getArgument(1));

        $this->assertTrue($containerBuilder->hasDefinition('manticore.index.simple_entity'));

        $this->assertTrue($containerBuilder->has('manticore.connection'));
        $def = $containerBuilder->getDefinition('manticore.connection');

        $this->assertTrue($def->hasMethodCall('setParams'));
        $this->assertSame(Connection::class, $def->getClass());

        $calls = $def->getMethodCalls();
        $setParams = $calls[0];

        $this->assertSame('manticore', $setParams[1][0]['host']);
        $this->assertSame(9306, $setParams[1][0]['port']);
    }
}
