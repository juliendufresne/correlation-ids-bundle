<?php

declare(strict_types=1);

namespace ManoManoTech\CorrelationIdBundle\Tests\DependencyInjection;

use ManoManoTech\CorrelationIdBundle\DependencyInjection\Configuration;
use ManoManoTech\CorrelationIdGuzzle\MiddlewareInterface;
use ManoManoTech\CorrelationIdHTTPlug\CorrelationIdHTTPlug;
use ManoManoTech\CorrelationIdMonolog\CorrelationIdProcessor;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MonologBundle\DependencyInjection\Compiler\AddProcessorsPass;
use Symfony\Component\Config\Definition\Processor;

/** @covers \ManoManoTech\CorrelationIdBundle\DependencyInjection\Configuration */
final class ConfigurationTest extends TestCase
{
    use PHPMock;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        PHPMock::defineFunctionMock('ManoManoTech\CorrelationIdBundle\DependencyInjection', 'class_exists');
        PHPMock::defineFunctionMock('ManoManoTech\CorrelationIdBundle\DependencyInjection', 'interface_exists');
    }

    /** @dataProvider provideClassThatDisablePluginByDefault */
    public function testDisablePlugin(
        string $pluginName,
        string $functionToMock,
        string $classThatShouldNotExists
    ): void {
        $classExists = $this->getFunctionMock('ManoManoTech\CorrelationIdBundle\DependencyInjection', $functionToMock);
        $classExists->expects(static::any())
                    ->willReturnCallback(
                        function ($arg) use ($classThatShouldNotExists) {
                            return $arg !== $classThatShouldNotExists;
                        }
                    );

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), []);
        static::assertFalse(
            $config['plugins'][$pluginName]['enabled'],
            "plugin $pluginName should not be enabled when class $classThatShouldNotExists does not exists"
        );
    }

    public function testDefaultValues(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), []);
        $expectedConfig = [
            'generator_service' => 'mm_correlation.generator.default',
            'header_names' => [
                'current' => 'current-correlation-id',
                'parent' => 'parent-correlation-id',
                'root' => 'root-correlation-id',
            ],
            'listeners' => [
                'console' => [
                    'enabled' => true,
                    'generator_service' => null,
                ],
                'request' => [
                    'enabled' => true,
                    'generator_service' => null,
                ],
                'response' => [
                    'enabled' => true,
                ],
            ],
            'plugins' => [
                'guzzle' => [
                    'enabled' => true,
                ],
                'httplug' => [
                    'enabled' => true,
                ],
                'monolog' => [
                    'enabled' => true,
                    'group_key' => null,
                    'skip_empty_values' => false,
                ],
            ],
        ];

        static::assertEquals($expectedConfig, $config);
    }

    public function provideClassThatDisablePluginByDefault(): array
    {
        return [
            ['guzzle', 'interface_exists', MiddlewareInterface::class],
            ['httplug', 'class_exists', CorrelationIdHTTPlug::class],
            ['monolog', 'class_exists', AddProcessorsPass::class],
            ['monolog', 'class_exists', CorrelationIdProcessor::class],
        ];
    }
}
