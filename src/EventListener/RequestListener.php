<?php

declare(strict_types=1);

namespace ManoManoTech\CorrelationIdBundle\EventListener;

use ManoManoTech\CorrelationId\CorrelationIdContainerInterface;
use ManoManoTech\CorrelationId\Factory\HeaderCorrelationIdContainerFactoryInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

final class RequestListener
{
    /** @var CorrelationIdContainerInterface */
    private $correlationIdContainer;
    /** @var HeaderCorrelationIdContainerFactoryInterface */
    private $correlationIdContainerFactory;

    public function __construct(
        CorrelationIdContainerInterface $correlationIdContainer,
        HeaderCorrelationIdContainerFactoryInterface $correlationIdContainerFactory
    ) {
        $this->correlationIdContainer = $correlationIdContainer;
        $this->correlationIdContainerFactory = $correlationIdContainerFactory;
    }

    public function onKernelRequest(GetResponseEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        $newCorrelationIdContainer = $this->correlationIdContainerFactory->create($request->headers->all());
        $this->correlationIdContainer->replace($newCorrelationIdContainer);
    }
}
