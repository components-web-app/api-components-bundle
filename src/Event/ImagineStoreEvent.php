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

use Liip\ImagineBundle\Binary\BinaryInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ImagineStoreEvent extends Event
{
    public BinaryInterface $binary;
    public string $path;
    public string $filter;

    public function __construct(BinaryInterface $binary, string $path, string $filter)
    {
        $this->binary = $binary;
        $this->path = $path;
        $this->filter = $filter;
    }
}
