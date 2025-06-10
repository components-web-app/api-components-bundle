<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\EventListener;

use Silverback\ApiComponentsBundle\Event\ResourceChangedEvent;
use Silverback\ApiComponentsBundle\HttpCache\ResourceChangedPropagatorInterface;

readonly class ResourceChangedEventListener
{
    public function __construct(private iterable $resourceChangedPropagators)
    {
    }

    public function __invoke(ResourceChangedEvent $event): void
    {
        /** @var ResourceChangedPropagatorInterface $resourceChangedPropagator */
        foreach ($this->resourceChangedPropagators as $resourceChangedPropagator) {
            $resourceChangedPropagator->add($event->getResource(), $event->getType());
        }
    }
}
