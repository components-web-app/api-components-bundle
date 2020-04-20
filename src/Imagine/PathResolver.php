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

namespace Silverback\ApiComponentBundle\Imagine;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PathResolver
{
    /** @var array */
    private $roots;

    public function __construct(
        array $roots = []
    ) {
        $this->roots = $roots;
    }

    public function resolve($path): string
    {
        foreach ($this->roots as $root) {
            if (0 === strpos($path, $root)) {
                return mb_substr($path, mb_strlen($root));
            }
        }

        return $path;
    }
}
