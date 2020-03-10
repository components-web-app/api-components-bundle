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

namespace Silverback\ApiComponentBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PreNormalizeEvent extends Event
{
    private object $resource;

    public function __construct(object $resource)
    {
        $this->resource = $resource;
    }

    public function getResource(): object
    {
        return $this->resource;
    }

    public function setResource(object $resource): self
    {
        $this->resource = $resource;

        return $this;
    }
}
