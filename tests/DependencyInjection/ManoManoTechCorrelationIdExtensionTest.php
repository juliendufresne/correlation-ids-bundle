<?php

declare(strict_types=1);

namespace ManoManoTech\CorrelationIdBundle\Tests\DependencyInjection;

use ManoManoTech\CorrelationId\CorrelationEntryName;
use ManoManoTech\CorrelationId\CorrelationIdContainer;
use ManoManoTech\CorrelationId\Factory\CorrelationIdContainerFactory;
use ManoManoTech\CorrelationId\Factory\HeaderCorrelationIdContainerFactory;
use ManoManoTech\CorrelationId\Generator\RamseyUuidGenerator;
use ManoManoTech\CorrelationIdBundle\DependencyInjection\ManoManoTechCorrelationIdExtension;
use ManoManoTech\CorrelationIdBundle\EventListener\ConsoleListener;
use ManoManoTech\CorrelationIdBundle\EventListener\RequestListener;
use ManoManoTech\CorrelationIdBundle\EventListener\ResponseListener;
use ManoManoTech\CorrelationIdMonolog\CorrelationIdProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** @covers \ManoManoTech\CorrelationIdBundle\DependencyInjection\ManoManoTechCorrelationIdExtension */
final class ManoManoTechCorrelationIdExtensionTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new ManoManoTechCorrelationIdExtension());
        $container->loadFromExtension('mm_correlation', []);
        $this->compileContainer($container);

        $services = [
            'mm_correlation.correlation_id_container' => CorrelationIdContainer::class,
            'mm_correlation.generator.ramsey_uuid' => RamseyUuidGenerator::class,
            'mm_correlation.header_name.default' => CorrelationEntryName::class,
            'mm_correlation.listener.console.factory' => CorrelationIdContainerFactory::class,
            'mm_correlation.listener.console' => ConsoleListener::class,
            'mm_correlation.listener.request.factory' => HeaderCorrelationIdContainerFactory::class,
            'mm_correlation.listener.request' => RequestListener::class,
            'mm_correlation.listener.response' => ResponseListener::class,
            'mm_correlation.monolog.processor' => CorrelationIdProcessor::class,
        ];

        foreach ($services as $serviceId => $className) {
            static::assertSame(
                $className,
                $container->getDefinition($serviceId)->getClass(),
                "Service $serviceId must be defined and it must be an instance of $className"
            );
        }

        static::assertTrue(
            $container->hasAlias('mm_correlation.generator.default'),
            'Default configuration should specify the mm_correlation.generator.default alias'
        );

        static::assertSame(
            'mm_correlation.generator.default',
            (string) $container->getDefinition('mm_correlation.listener.console.factory')->getArgument(0),
            sprintf(
                'The generator defined for the class %s must be the default one',
                CorrelationIdContainerFactory::class
            )
        );
        static::assertSame(
            'mm_correlation.generator.default',
            (string) $container->getDefinition('mm_correlation.listener.request.factory')->getArgument(0),
            sprintf(
                'The generator defined for the class %s must be the default one',
                HeaderCorrelationIdContainerFactory::class
            )
        );
    }

    public function testRequestListenerWithDedicatedHeaders(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new ManoManoTechCorrelationIdExtension());
        $container->loadFromExtension(
            'mm_correlation',
            [
                'listeners' => [
                    'request' => [
                        'enabled' => true,
                        'header_names' => [
                            'parent' => 'foo',
                            'root' => 'bar',
                        ],
                    ],
                ],
            ]
        );
        $this->compileContainer($container);
        static::assertTrue($container->hasDefinition('mm_correlation.listener.request.header_name'));
    }

    public function testResponseListenerWithDedicatedHeaders(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new ManoManoTechCorrelationIdExtension());
        $container->loadFromExtension(
            'mm_correlation',
            [
                'listeners' => [
                    'response' => [
                        'enabled' => true,
                        'header_names' => [
                            'parent' => 'foo',
                            'root' => 'bar',
                        ],
                    ],
                ],
            ]
        );
        $this->compileContainer($container);
        static::assertTrue($container->hasDefinition('mm_correlation.listener.response.header_name'));
    }

    public function testGuzzleWithDedicatedHeaders(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new ManoManoTechCorrelationIdExtension());
        $container->loadFromExtension(
            'mm_correlation',
            [
                'plugins' => [
                    'guzzle' => [
                        'enabled' => true,
                        'header_names' => [
                            'parent' => 'foo',
                            'root' => 'bar',
                        ],
                    ],
                ],
            ]
        );
        $this->compileContainer($container);
        static::assertTrue($container->hasDefinition('mm_correlation.plugin.guzzle.header_name'));
    }

    public function testHTTPlugWithDedicatedHeaders(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new ManoManoTechCorrelationIdExtension());
        $container->loadFromExtension(
            'mm_correlation',
            [
                'plugins' => [
                    'httplug' => [
                        'enabled' => true,
                        'header_names' => [
                            'parent' => 'foo',
                            'root' => 'bar',
                        ],
                    ],
                ],
            ]
        );
        $this->compileContainer($container);
        static::assertTrue($container->hasDefinition('mm_correlation.plugin.httplug.header_name'));
    }

    public function testMonologWithDedicatedHeaders(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new ManoManoTechCorrelationIdExtension());
        $container->loadFromExtension(
            'mm_correlation',
            [
                'plugins' => [
                    'monolog' => [
                        'enabled' => true,
                        'header_names' => [
                            'current' => 'foo',
                            'parent' => 'bar',
                            'root' => 'baz',
                        ],
                    ],
                ],
            ]
        );
        $this->compileContainer($container);
        static::assertTrue($container->hasDefinition('mm_correlation.plugin.monolog.header_name'));
    }

    private function compileContainer(ContainerBuilder $container): void
    {
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->compile();
    }
}
