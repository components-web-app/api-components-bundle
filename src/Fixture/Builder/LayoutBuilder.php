<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Builder;

use Silverback\ApiComponentsBundle\Entity\Core\Layout;

class LayoutBuilder
{
    /** @var array<string, GroupBuilder> */
    private array $groupBuilders = [];

    public function __construct(private readonly Layout $layout)
    {
    }

    public function group(string $name, array $allow = [], ?\Closure $configure = null): GroupBuilder
    {
        if (!isset($this->groupBuilders[$name])) {
            $this->groupBuilders[$name] = new GroupBuilder($name, $allow);
        }
        if (null !== $configure) {
            $configure($this->groupBuilders[$name]);
        }

        return $this->groupBuilders[$name];
    }

    public function getLayout(): Layout
    {
        return $this->layout;
    }

    /** @return array<string, GroupBuilder> */
    public function getGroupBuilders(): array
    {
        return $this->groupBuilders;
    }
}
