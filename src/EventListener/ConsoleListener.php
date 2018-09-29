<?php

declare(strict_types=1);

namespace ManoManoTech\CorrelationIdBundle\EventListener;

use ManoManoTech\CorrelationId\CorrelationIdContainerInterface;
use ManoManoTech\CorrelationId\Factory\CorrelationIdContainerFactoryInterface;

final class ConsoleListener
{
    /** @var CorrelationIdContainerInterface */
    private $correlationIdContainer;
    /** @var CorrelationIdContainerFactoryInterface */
    private $correlationIdContainerFactory;

    public function __construct(
        CorrelationIdContainerInterface $correlationIdContainer,
        CorrelationIdContainerFactoryInterface $correlationIdContainerFactory
    ) {
        $this->correlationIdContainer = $correlationIdContainer;
        $this->correlationIdContainerFactory = $correlationIdContainerFactory;
    }

    public function onConsoleCommand(): void
    {
        $this->correlationIdContainer->replace(
            $this->correlationIdContainerFactory->create(null, null)
        );
    }
}
