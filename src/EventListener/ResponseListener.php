<?php

declare(strict_types=1);

namespace ManoManoTech\CorrelationIdBundle\EventListener;

use ManoManoTech\CorrelationId\CorrelationEntryNameInterface;
use ManoManoTech\CorrelationId\CorrelationIdContainerInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

final class ResponseListener
{
    /** @var CorrelationEntryNameInterface */
    private $correlationEntryName;
    /** @var CorrelationIdContainerInterface */
    private $correlationIdContainer;

    public function __construct(
        CorrelationIdContainerInterface $correlationIdContainer,
        CorrelationEntryNameInterface $correlationEntryName
    ) {
        $this->correlationIdContainer = $correlationIdContainer;
        $this->correlationEntryName = $correlationEntryName;
    }

    public function onKernelResponse(FilterResponseEvent $event): void
    {
        $event->getResponse()->headers->add(
            [
                $this->correlationEntryName->current() => $this->correlationIdContainer->current(),
                $this->correlationEntryName->parent() => $this->correlationIdContainer->parent(),
                $this->correlationEntryName->root() => $this->correlationIdContainer->root(),
            ]
        );
    }
}
