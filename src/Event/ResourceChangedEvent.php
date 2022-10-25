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

namespace Silverback\ApiComponentsBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ResourceChangedEvent extends Event
{
    public function __construct(private readonly object $resource, string $type)
    {
    }

    public function getResource(): object
    {
        return $this->resource;
    }

    public function getType(): string
    {
        return $this->getType();
    }
}
