<?php

declare(strict_types=1);

namespace ManoManoTech\CorrelationIdBundle;

use ManoManoTech\CorrelationIdBundle\DependencyInjection\ManoManoTechCorrelationIdExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class ManoManoTechCorrelationIdBundle extends Bundle
{
    public function getContainerExtension(): ManoManoTechCorrelationIdExtension
    {
        if (null === $this->extension) {
            $this->extension = new ManoManoTechCorrelationIdExtension();
        }

        return $this->extension;
    }
}
