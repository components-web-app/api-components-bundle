<?php

namespace Silverback\ApiComponentBundle\DataModifier;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Factory\RouteFactory;

class RouteAwareModifier extends AbstractModifier
{
    /**
     * @param RouteAwareInterface $object
     * @param array $context
     * @param null|string $format
     * @return object|void
     */
    public function process($object, array $context = array(), ?string $format = null)
    {
        /** @var RouteFactory $routeFactory */
        $routeFactory = $this->container->get(RouteFactory::class);
        /** @var EntityManagerInterface $em */
        $em = $this->container->get(EntityManagerInterface::class);
        $routeFactory->createPageRoute($object, $em);
        $em->flush();
    }

    public function supportsData($data): bool
    {
        return $data instanceof RouteAwareInterface;
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . RouteFactory::class,
            '?' . EntityManagerInterface::class
        ];
    }
}
