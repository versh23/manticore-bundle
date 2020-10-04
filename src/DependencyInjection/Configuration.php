<?php

declare(strict_types=1);

namespace Versh23\ManticoreBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Versh23\ManticoreBundle\IndexConfiguration;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('manticore');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('client')
                    ->children()
                        ->scalarNode('transport')->defaultValue('Http')->end()
                        ->scalarNode('host')->defaultValue('localhost')->end()
                        ->scalarNode('port')->defaultValue('9308')->end()
                        ->scalarNode('timeout')->defaultValue(300)->end()
                        ->scalarNode('connect_timeout')->defaultValue(null)->end()
                        ->scalarNode('proxy')->defaultValue(null)->end()
                        ->scalarNode('username')->defaultValue(null)->end()
                        ->scalarNode('password')->defaultValue(null)->end()
                        ->scalarNode('persistent')->defaultValue(true)->end()
                    ->end()
                ->end()
                ->arrayNode('indexes')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('options')
                                ->scalarPrototype()->end()
                            ->end()
                            ->scalarNode('class')->end()
                            ->arrayNode('fields')
                                ->beforeNormalization()
                                    ->always(function ($fields) {
                                        foreach ($fields as $name => &$field) {
                                            if (is_string($field)) {
                                                $field = [
                                                    'type' => $field,
                                                ];
                                            }

                                            if (!isset($field['type'])) {
                                                $field['type'] = IndexConfiguration::TYPE_TEXT;
                                            }

                                            if (!isset($field['property'])) {
                                                $field['property'] = $name;
                                            }
                                        }

                                        return $fields;
                                    })
                                ->end()
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('property')->end()
                                        ->scalarNode('type')
                                            ->validate()
                                                ->ifTrue(function ($type) {return !in_array($type, IndexConfiguration::TYPES); })
                                                ->thenInvalid('Type is not valid. Must be ['.implode(', ', IndexConfiguration::TYPES).']')
                                            ->end()
                                        ->end()
                                        //TODO add validation
                                        // options - an array of options of the field, text can have indexed,stored (default is both) and string can have attribute (default) and indexed
                                        ->arrayNode('options')
                                            ->scalarPrototype()->end()
                                        ->end()
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
