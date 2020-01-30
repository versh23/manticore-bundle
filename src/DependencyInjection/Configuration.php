<?php


namespace Versh23\ManticoreBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('manticore');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('host')->defaultValue('localhost')->end()
                ->scalarNode('port')->defaultValue(9306)->end()
                ->arrayNode('indexes')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('class')->end()
                            ->arrayNode('fields')
                                ->scalarPrototype()->end()
                            ->end()
                            ->arrayNode('attributes')
                                ->beforeNormalization()
                                    ->always(function ($attributes) {
                                        foreach ($attributes as $name => &$attribute) {
                                            if (is_string($attribute)) {
                                                $attribute = [
                                                    'type' => $attribute
                                                ];
                                            }
                                            if (!isset($attribute['property'])) {
                                                $attribute['property'] = $name;
                                            }
                                        }
                                        return $attributes;
                                    })
                                ->end()
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('property')->end()
                                        ->scalarNode('type')->end()
                                    ->end()
                                    ->validate()
                                        ->ifTrue(function ($v) {return !isset($v['type']);})
                                        ->thenInvalid('Type is not defined')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}