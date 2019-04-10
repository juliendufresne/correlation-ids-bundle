<?php

declare(strict_types=1);

namespace ManoManoTech\CorrelationIdBundle\DependencyInjection;

use ManoManoTech\CorrelationIdGuzzle\MiddlewareInterface;
use ManoManoTech\CorrelationIdHTTPlug\CorrelationIdHTTPlug;
use ManoManoTech\CorrelationIdMonolog\CorrelationIdProcessor;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\AddProcessorsPass;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('mm_correlation');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $children = $rootNode->children();

        $children->append($this->addGeneratorNode(true));
        $children->append($this->addHeaderNode(true, true));
        $children->append($this->addListenerNode());
        $children->append($this->addPluginNode());

        return $treeBuilder;
    }

    private function addGeneratorNode(bool $withDefault = false): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition('generator_service');
        $node->info('Service ID used to generate unique identifiers');

        $withDefault ? $node->defaultValue('mm_correlation.generator.default') : $node->defaultNull();

        return $node;
    }

    private function addHeaderNode(bool $includeCurrentHeaderName = true, bool $withDefault = false): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('header_names');
        /** @var ArrayNodeDefinition $node */
        $node = $treeBuilder->getRootNode();
        if ($withDefault) {
            $node->addDefaultsIfNotSet();
        }
        $children = $node->children();

        if ($includeCurrentHeaderName) {
            $current = $children->scalarNode('current')->info(
                'Header name for the correlation id of the current process'
            );
            $withDefault ? $current->defaultValue('current-correlation-id') : $current->defaultNull();
        }

        $parent = $children->scalarNode('parent')->info('Header name for the correlation id of the parent process');
        $withDefault ? $parent->defaultValue('parent-correlation-id') : $parent->defaultNull();

        $root = $children->scalarNode('root')->info('Header name for the correlation id of the root process');
        $withDefault ? $root->defaultValue('root-correlation-id') : $root->defaultNull();

        return $node;
    }

    private function addListenerNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('listeners');
        /** @var ArrayNodeDefinition $listenerNode */
        $listenerNode = $treeBuilder->getRootNode();
        $listenerNode->addDefaultsIfNotSet()
                     ->children()
                     ->append($this->addConsoleListenerNode())
                     ->append($this->addRequestListenerNode())
                     ->append($this->addResponseListenerNode());

        return $listenerNode;
    }

    private function addConsoleListenerNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('console');
        /** @var ArrayNodeDefinition $node */
        $node = $treeBuilder->getRootNode();
        $children = $node
            ->info('configure the console listener')
            ->canBeDisabled()
            ->addDefaultsIfNotSet()
            ->children();

        $children->append($this->addGeneratorNode());

        return $node;
    }

    private function addRequestListenerNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('request');
        /** @var ArrayNodeDefinition $node */
        $node = $treeBuilder->getRootNode();
        $children = $node
            ->info('configure the request listener')
            ->canBeDisabled()
            ->addDefaultsIfNotSet()
            ->children();

        $children->append($this->addGeneratorNode());
        $children->append($this->addHeaderNode(false));

        return $node;
    }

    private function addResponseListenerNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('response');
        /** @var ArrayNodeDefinition $node */
        $node = $treeBuilder->getRootNode();
        $children = $node
            ->info('configure the response listener')
            ->canBeDisabled()
            ->addDefaultsIfNotSet()
            ->children();

        $children->append($this->addHeaderNode());

        return $node;
    }

    private function addPluginNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('plugins');
        /** @var ArrayNodeDefinition $listenerNode */
        $listenerNode = $treeBuilder->getRootNode();
        $listenerNode->addDefaultsIfNotSet()
                     ->children()
                     ->append($this->addGuzzleNode())
                     ->append($this->addHTTPlugNode())
                     ->append($this->addMonologNode());

        return $listenerNode;
    }

    private function addGuzzleNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('guzzle');
        /** @var ArrayNodeDefinition $node */
        $node = $treeBuilder->getRootNode();

        interface_exists(MiddlewareInterface::class) ? $node->canBeDisabled() : $node->canBeEnabled();

        $node->info('require manomano-tech/request-correlation-guzzle');

        $children = $node->addDefaultsIfNotSet()->children();

        $children->append($this->addHeaderNode());

        return $node;
    }

    private function addHTTPlugNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('httplug');
        /** @var ArrayNodeDefinition $node */
        $node = $treeBuilder->getRootNode();

        class_exists(CorrelationIdHTTPlug::class) ? $node->canBeDisabled() : $node->canBeEnabled();

        $children = $node
            ->info('require manomano-tech/request-correlation-httplug')
            ->addDefaultsIfNotSet()
            ->children();

        $children->append($this->addHeaderNode());

        return $node;
    }

    private function addMonologNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('monolog');
        /** @var ArrayNodeDefinition $node */
        $node = $treeBuilder->getRootNode();

        if (class_exists(AddProcessorsPass::class) && class_exists(CorrelationIdProcessor::class)) {
            $node->canBeDisabled();
        } else {
            $node->canBeEnabled();
        }

        $children = $node->info('require manomano-tech/request-correlation-monolog and symfony/monolog-bundle')
                         ->addDefaultsIfNotSet()
                         ->children();

        $children->scalarNode('group_key')
                 ->info('If set, will group correlation id into one array')
                 ->defaultNull();
        $children->booleanNode('skip_empty_values')
                 ->info('Do not add entry for empty values')
                 ->defaultFalse();

        $children->append($this->addHeaderNode());

        return $node;
    }
}
