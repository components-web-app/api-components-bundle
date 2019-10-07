<?php

namespace Silverback\ApiComponentBundle\DataTransformer;

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

abstract class AbstractDataTransformer implements DataTransformerInterface, ServiceSubscriberInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    abstract public static function getSubscribedServices(): array;
}
