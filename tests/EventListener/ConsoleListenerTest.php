<?php

declare(strict_types=1);

namespace ManoManoTech\CorrelationIdBundle\Tests\EventListener;

use ManoManoTech\CorrelationId\CorrelationIdContainer;
use ManoManoTech\CorrelationId\Factory\CorrelationIdContainerFactoryInterface;
use ManoManoTech\CorrelationIdBundle\EventListener\ConsoleListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** @covers \ManoManoTech\CorrelationIdBundle\EventListener\ConsoleListener */
final class ConsoleListenerTest extends TestCase
{
    public function testOnConsoleCommand(): void
    {
        $correlationIdContainer = new CorrelationIdContainer('foo', 'bar', 'baz');
        /** @var CorrelationIdContainerFactoryInterface|MockObject $correlationIdContainerFactory */
        $correlationIdContainerFactory = $this->createMock(CorrelationIdContainerFactoryInterface::class);
        $correlationIdContainerFactory->expects(static::once())
                                      ->method('create')
                                      ->willReturn(
                                          new CorrelationIdContainer('new-foo', null, null)
                                      );

        $listener = new ConsoleListener($correlationIdContainer, $correlationIdContainerFactory);
        $listener->onConsoleCommand();

        static::assertSame('new-foo', $correlationIdContainer->current());
        static::assertNull($correlationIdContainer->parent());
        static::assertNull($correlationIdContainer->root());
    }
}
