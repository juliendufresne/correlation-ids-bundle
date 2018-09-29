<?php

declare(strict_types=1);

namespace ManoManoTech\CorrelationIdBundle\Tests\EventListener;

use ManoManoTech\CorrelationId\CorrelationEntryNameInterface;
use ManoManoTech\CorrelationId\CorrelationIdContainerInterface;
use ManoManoTech\CorrelationIdBundle\EventListener\ResponseListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/** @covers \ManoManoTech\CorrelationIdBundle\EventListener\ResponseListener */
final class ResponseListenerTest extends TestCase
{
    public function testOnKernelRequest(): void
    {
        // prepare event
        $request = new Request();
        $response = new Response();
        /** @var HttpKernelInterface $httpKernelMock */
        $httpKernelMock = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $event = new FilterResponseEvent($httpKernelMock, $request, HttpKernelInterface::MASTER_REQUEST, $response);

        $correlationIdContainer = $this->createMock(CorrelationIdContainerInterface::class);
        $correlationIdContainer->expects(self::any())
                               ->method('current')
                               ->willReturn('foo');
        $correlationIdContainer->expects(self::any())
                               ->method('parent')
                               ->willReturn('bar');
        $correlationIdContainer->expects(self::any())
                               ->method('root')
                               ->willReturn('baz');
        $correlationEntryName = $this->createMock(CorrelationEntryNameInterface::class);
        $correlationEntryName->expects(self::any())
                             ->method('current')
                             ->willReturn('Correlation-Id');
        $correlationEntryName->expects(self::any())
                             ->method('parent')
                             ->willReturn('Parent-Correlation-Id');
        $correlationEntryName->expects(self::any())
                             ->method('root')
                             ->willReturn('Root-Correlation-Id');

        $listener = new ResponseListener($correlationIdContainer, $correlationEntryName);
        $listener->onKernelResponse($event);

        static::assertArrayHasKey('correlation-id', $response->headers->all());
        static::assertEquals('foo', $response->headers->get('correlation-id'));
        static::assertEquals('bar', $response->headers->get('parent-correlation-id'));
        static::assertEquals('baz', $response->headers->get('root-correlation-id'));
    }
}
