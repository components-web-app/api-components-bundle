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

namespace Silverback\ApiComponentsBundle\Entity\Core;

/**
 * @author Daniel West <daniel@silverback.is>
 */
interface RoutableInterface
{
    public function getTitle(): ?string;

    public function getRoute(): ?Route;

    /**
     * @return static
     */
    public function setRoute(?Route $route);

    public function getParentRoute(): ?Route;

    /**
     * @return static
     */
    public function setParentRoute(?Route $parentRoute);
}
