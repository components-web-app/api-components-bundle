<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventListener;

use Psr\Container\ContainerInterface;
use Silverback\ApiComponentBundle\Entity\Component\Collection;
use Silverback\ApiComponentBundle\Event\PreNormalizeEvent;
use Silverback\ApiComponentBundle\Serializer\DataTransformer\CollectionTransformer;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PreNormalizeListener implements ServiceSubscriberInterface
{
    private ContainerInterface $locator;

    public function __construct(ContainerInterface $locator)
    {
        $this->locator = $locator;
    }

    public static function getSubscribedServices(): array
    {
        return [
            CollectionTransformer::class => '?' . CollectionTransformer::class,
        ];
    }

    public function preNormalize(PreNormalizeEvent $event): void
    {
        $resource = $event->getResource();
        if ($resource instanceof Collection) {
            /** @var CollectionTransformer $collectionTransformer */
            $collectionTransformer = $this->locator->get(CollectionTransformer::class);
            $collectionTransformer->transform($resource);
            $event->setResource($resource);
        }
    }
}
