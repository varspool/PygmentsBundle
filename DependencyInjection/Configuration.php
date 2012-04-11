<?php

namespace Varspool\PygmentsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('varspool_pygments');

        $rootNode
            ->children()
                ->scalarNode('bin')->defaultValue('/usr/bin/pygmentize')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}