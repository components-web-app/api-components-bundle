<?php

namespace Silverback\ApiComponentBundle\EventSubscriber\ApiPlatform;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractSubscriber implements ServiceSubscriberInterface, EventSubscriberInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    abstract public static function getSubscribedEvents(): array;

    abstract public static function getSubscribedServices(): array;
}
