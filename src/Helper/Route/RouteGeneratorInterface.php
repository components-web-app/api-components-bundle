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

namespace Silverback\ApiComponentsBundle\Helper\Route;

use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

/**
 * @author Daniel West <daniel@silverback.is>
 */
interface RouteGeneratorInterface
{
    public function create(RoutableInterface $object, Route $route = null): Route;

    public function createRedirect(string $fromPath, Route $targetRoute): Route;
}
