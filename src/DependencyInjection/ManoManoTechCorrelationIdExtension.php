<?php

declare(strict_types=1);

namespace ManoManoTech\CorrelationIdBundle\DependencyInjection;

use ManoManoTech\CorrelationId\CorrelationEntryName;
use ManoManoTech\CorrelationId\CorrelationIdContainer;
use ManoManoTech\CorrelationId\Factory\CorrelationIdContainerFactory;
use ManoManoTech\CorrelationId\Factory\HeaderCorrelationIdContainerFactory;
use ManoManoTech\CorrelationId\Generator\RamseyUuidGenerator;
use ManoManoTech\CorrelationIdBundle\EventListener\ConsoleListener;
use ManoManoTech\CorrelationIdBundle\EventListener\RequestListener;
use ManoManoTech\CorrelationIdBundle\EventListener\ResponseListener;
use ManoManoTech\CorrelationIdGuzzle\CorrelationIdMiddleware;
use ManoManoTech\CorrelationIdGuzzle\GuzzleClientFactory;
use ManoManoTech\CorrelationIdGuzzle\MiddlewareInterface;
use ManoManoTech\CorrelationIdHTTPlug\CorrelationIdHTTPlug;
use ManoManoTech\CorrelationIdHTTPlug\HttpClientFactory;
use ManoManoTech\CorrelationIdMonolog\CorrelationIdProcessor;
use RuntimeException;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\AddProcessorsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class ManoManoTechCorrelationIdExtension extends Extension
{
    private const CORRELATION_ID_CONTAINER_SERVICE_NAME = 'mm_correlation.correlation_id_container';
    private const DEFAULT_GENERATOR_SERVICE_NAME = 'mm_correlation.generator.default';
    private const DEFAULT_HEADER_NAME_SERVICE_NAME = 'mm_correlation.header_name.default';

    public function getAlias(): string
    {
        return 'mm_correlation';
    }

    /** @param mixed[] $configs */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        if (null === $configuration) {
            return;
        }

        $config = $this->processConfiguration($configuration, $configs);

        $this->createCorrelationIdContainer($container)
             ->configureDefaultGenerator($container, $config['generator_service'])
             ->configureHeaderNameService($container, $config['header_names'], self::DEFAULT_HEADER_NAME_SERVICE_NAME)
             ->configureEventListeners($container, $config['listeners'])
             ->configurePlugins($container, $config['plugins']);
    }

    private function createCorrelationIdContainer(ContainerBuilder $container): self
    {
        $definition = new Definition(
            CorrelationIdContainer::class,
            ['should-be-overwritten-by-a-listener', null, null]
        );
        $definition->setPrivate(true);

        $container->setDefinition(self::CORRELATION_ID_CONTAINER_SERVICE_NAME, $definition);

        return $this;
    }

    private function getCorrelationIdContainer(): Reference
    {
        return new Reference(self::CORRELATION_ID_CONTAINER_SERVICE_NAME);
    }

    private function configureDefaultGenerator(ContainerBuilder $container, ?string $generatorService): self
    {
        $ramseyUuidServiceName = 'mm_correlation.generator.ramsey_uuid';

        $definition = new Definition(RamseyUuidGenerator::class);
        $definition->setPrivate(true);

        $container->setDefinition($ramseyUuidServiceName, $definition);

        if (null === $generatorService || self::DEFAULT_GENERATOR_SERVICE_NAME === $generatorService) {
            $container->setAlias(self::DEFAULT_GENERATOR_SERVICE_NAME, $ramseyUuidServiceName);
        } else {
            $container->setAlias(self::DEFAULT_GENERATOR_SERVICE_NAME, $generatorService);
        }

        return $this;
    }

    private function configureHeaderNameService(ContainerBuilder $container, array $config, string $serviceId): self
    {
        $definition = new Definition(
            CorrelationEntryName::class,
            [
                $config['current'],
                $config['parent'],
                $config['root'],
            ]
        );
        $definition->setPrivate(true);

        $container->setDefinition($serviceId, $definition);

        return $this;
    }

    private function configureEventListeners(ContainerBuilder $container, array $config): self
    {
        if (true === $config['console']['enabled']) {
            $this->configureConsoleListener($container, $config['console']);
        }

        if (true === $config['request']['enabled']) {
            $this->configureRequestListener($container, $config['request']);
        }

        if (true === $config['response']['enabled']) {
            $this->configureResponseListener($container, $config['response']);
        }

        return $this;
    }

    private function configureConsoleListener(ContainerBuilder $container, array $config): self
    {
        if (null === $config['generator_service']) {
            $config['generator_service'] = self::DEFAULT_GENERATOR_SERVICE_NAME;
        }

        // create the factory specific for the console listener
        $factoryDefinition = new Definition(
            CorrelationIdContainerFactory::class,
            [new Reference($config['generator_service'])]
        );
        $factoryDefinition->setPrivate(true);

        $container->setDefinition('mm_correlation.listener.console.factory', $factoryDefinition);

        $definition = new Definition(
            ConsoleListener::class, [
                $this->getCorrelationIdContainer(),
                $factoryDefinition,
            ]
        );

        $definition->setPrivate(true)
                   ->addTag('kernel.event_listener', ['event' => 'console.command']);

        $container->setDefinition('mm_correlation.listener.console', $definition);

        return $this;
    }

    private function configureRequestListener(ContainerBuilder $container, array $config): self
    {
        if (null === $config['generator_service']) {
            $config['generator_service'] = self::DEFAULT_GENERATOR_SERVICE_NAME;
        }

        $headerServiceName = self::DEFAULT_HEADER_NAME_SERVICE_NAME;
        if (isset($config['header_names'])) {
            $config['header_names']['current'] = '';
            $headerServiceName = 'mm_correlation.listener.request.header_name';
            $this->configureHeaderNameService($container, $config['header_names'], $headerServiceName);
        }

        // create the factory specific for the request listener
        $factoryDefinition = new Definition(
            HeaderCorrelationIdContainerFactory::class,
            [
                new Reference($config['generator_service']),
                new Reference($headerServiceName),
            ]
        );
        $factoryDefinition->setPrivate(true);
        $container->setDefinition('mm_correlation.listener.request.factory', $factoryDefinition);

        $definition = new Definition(
            RequestListener::class, [
                $this->getCorrelationIdContainer(),
                $factoryDefinition,
            ]
        );

        $definition->setPrivate(true)
                   ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'priority' => 255]);

        $container->setDefinition('mm_correlation.listener.request', $definition);

        return $this;
    }

    private function configureResponseListener(ContainerBuilder $container, array $config): self
    {
        $headerServiceName = self::DEFAULT_HEADER_NAME_SERVICE_NAME;
        if (isset($config['header_names'])) {
            $config['header_names']['current'] = '';
            $headerServiceName = 'mm_correlation.listener.response.header_name';
            $this->configureHeaderNameService($container, $config['header_names'], $headerServiceName);
        }

        $definition = new Definition(
            ResponseListener::class, [
                $this->getCorrelationIdContainer(),
                new Reference($headerServiceName),
            ]
        );

        $definition->setPrivate(true)
                   ->addTag('kernel.event_listener', ['event' => 'kernel.response']);

        $container->setDefinition('mm_correlation.listener.response', $definition);

        return $this;
    }

    private function configurePlugins(ContainerBuilder $container, array $config): self
    {
        if (true === $config['guzzle']['enabled']) {
            $this->configureGuzzlePlugin($container, $config['guzzle']);
        }

        if (true === $config['httplug']['enabled']) {
            $this->configureHTTPlugPlugin($container, $config['httplug']);
        }

        if (true === $config['monolog']['enabled']) {
            $this->configureMonologPlugin($container, $config['monolog']);
        }

        return $this;
    }

    private function configureGuzzlePlugin(ContainerBuilder $container, array $config): self
    {
        if (!interface_exists(MiddlewareInterface::class)) {
            throw new RuntimeException(
                'You need the manomano-tech/request-correlation-guzzle package to enable the guzzle plugin'
            );
        }

        $headerServiceName = self::DEFAULT_HEADER_NAME_SERVICE_NAME;
        if (isset($config['header_names'])) {
            $config['header_names']['current'] = '';
            $headerServiceName = 'mm_correlation.plugin.guzzle.header_name';
            $this->configureHeaderNameService($container, $config['header_names'], $headerServiceName);
        }

        $definition = new Definition(
            CorrelationIdMiddleware::class, [
                $this->getCorrelationIdContainer(),
                new Reference($headerServiceName),
            ]
        );
        $definition->setPrivate(true);

        $container->setDefinition('mm_correlation.guzzle.middleware', $definition);
        $container->setAlias(MiddlewareInterface::class, 'mm_correlation.guzzle.middleware');

        // client

        $definition = new Definition(
            GuzzleClientFactory::class,
            [
                $this->getCorrelationIdContainer(),
                new Reference($headerServiceName),
            ]
        );
        $definition->setPrivate(true);
        $container->setDefinition('mm_correlation.guzzle.client', $definition);
        $container->setAlias(GuzzleClientFactory::class, 'mm_correlation.guzzle.client');

        return $this;
    }

    private function configureHTTPlugPlugin(ContainerBuilder $container, array $config): self
    {
        if (!class_exists(CorrelationIdHTTPlug::class)) {
            throw new RuntimeException(
                'You need the manomano-tech/request-correlation-httplug package to enable the httplug plugin'
            );
        }

        $headerServiceName = self::DEFAULT_HEADER_NAME_SERVICE_NAME;
        if (isset($config['header_names'])) {
            $config['header_names']['current'] = '';
            $headerServiceName = 'mm_correlation.plugin.httplug.header_name';
            $this->configureHeaderNameService($container, $config['header_names'], $headerServiceName);
        }

        $definition = new Definition(
            CorrelationIdHTTPlug::class,
            [
                $this->getCorrelationIdContainer(),
                new Reference($headerServiceName),
            ]
        );
        $definition->setPrivate(true);

        $container->setDefinition('mm_correlation.httplug.plugin', $definition);
        $container->setAlias(CorrelationIdHTTPlug::class, 'mm_correlation.httplug.plugin');

        // client

        $definition = new Definition(
            HttpClientFactory::class,
            [
                $this->getCorrelationIdContainer(),
                new Reference($headerServiceName),
            ]
        );
        $definition->setPrivate(true);
        $container->setDefinition('mm_correlation.httplug.client_factory', $definition);
        $container->setAlias(HttpClientFactory::class, 'mm_correlation.httplug.client_factory');

        return $this;
    }

    private function configureMonologPlugin(ContainerBuilder $container, array $config): self
    {
        if (!class_exists(CorrelationIdProcessor::class)) {
            throw new RuntimeException(
                'You need the manomano-tech/request-correlation-httplug package to enable the monolog plugin'
            );
        }

        if (!class_exists(AddProcessorsPass::class)) {
            throw new RuntimeException(
                'You need the symfony/monolog-bundle package to enable the monolog plugin'
            );
        }

        $headerServiceName = self::DEFAULT_HEADER_NAME_SERVICE_NAME;
        if (isset($config['header_names'])) {
            $headerServiceName = 'mm_correlation.plugin.monolog.header_name';
            $this->configureHeaderNameService($container, $config['header_names'], $headerServiceName);
        }

        $definition = new Definition(
            CorrelationIdProcessor::class,
            [
                $this->getCorrelationIdContainer(),
                new Reference($headerServiceName),
            ]
        );
        $definition->setPrivate(true)
                   ->addTag('monolog.processor');

        if (null !== $config['group_key'] && '' !== $config['group_key']) {
            $definition->addMethodCall('groupCorrelationIdsInOneArrayWithKey', [$config['group_key']]);
        }

        if (true === $config['skip_empty_values']) {
            $definition->addMethodCall('skipEmptyValues');
        }

        $container->setDefinition('mm_correlation.monolog.processor', $definition);

        return $this;
    }
}
