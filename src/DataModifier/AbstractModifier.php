<?php

namespace Silverback\ApiComponentBundle\DataModifier;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;

abstract class AbstractModifier implements DataModifierInterface, ServiceSubscriberInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    abstract public function process($object, array $context = array());

    abstract public function supportsData($data): bool;

    abstract public static function getSubscribedServices(): array;
}
