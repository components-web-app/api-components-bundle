<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Annotation;

use Silverback\ApiComponentsBundle\Exception\InvalidRoutePrefixException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class RoutePrefix
{
    public function __construct(public readonly string $prefix)
    {
        if (!preg_match('#\A/.*[^/]\z#s', $prefix)) {
            throw new InvalidRoutePrefixException(\sprintf('The route prefix "%s" is invalid: it must start with a slash, must not end with one and cannot be a lone slash.', $prefix));
        }
    }
}
