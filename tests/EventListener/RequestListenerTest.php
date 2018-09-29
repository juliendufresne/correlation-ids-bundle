<?php

declare(strict_types=1);

namespace ManoManoTech\CorrelationIdBundle\Tests\EventListener;

use ManoManoTech\CorrelationId\CorrelationIdContainer;
use ManoManoTech\CorrelationId\Factory\HeaderCorrelationIdContainerFactoryInterface;
use ManoManoTech\CorrelationIdBundle\EventListener\RequestListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/** @covers \ManoManoTech\CorrelationIdBundle\EventListener\RequestListener */
final class RequestListenerTest extends TestCase
{
    public function testOnKernelRequest(): void
    {
        // prepare event
        $request = new Request();
        $response = new Response();
        /** @var HttpKernelInterface $httpKernelMock */
        $httpKernelMock = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $event = new GetResponseEvent($httpKernelMock, $request, HttpKernelInterface::MASTER_REQUEST);
        $event->setResponse($response);

        $correlationIdContainer = new CorrelationIdContainer('foo', 'bar', 'baz');
        /** @var HeaderCorrelationIdContainerFactoryInterface|MockObject $correlationIdContainerFactory */
        $correlationIdContainerFactory = $this->createMock(HeaderCorrelationIdContainerFactoryInterface::class);
        $correlationIdContainerFactory->expects(static::once())
                                      ->method('create')
                                      ->willReturn(
                                          new CorrelationIdContainer('new-foo', 'new-bar', 'new-baz')
                                      );

        $listener = new RequestListener($correlationIdContainer, $correlationIdContainerFactory);
        $listener->onKernelRequest($event);

        static::assertEquals('new-foo', $correlationIdContainer->current());
        static::assertEquals('new-bar', $correlationIdContainer->parent());
        static::assertEquals('new-baz', $correlationIdContainer->root());
    }
}
