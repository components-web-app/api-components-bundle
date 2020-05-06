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

use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 *
 * @internal
 */
abstract class AbstractPage
{
    use IdTrait;
    use TimestampedTrait;

    public ?Route $route;

    /**
     * This will be se so that when auto-generating a route for a newly created
     * PageTemplate / PageData, we can prepend parent routes.
     */
    public ?Route $parentRoute;

    /**
     * If true, then the PageTemplate/PageData is nested within the parentRoute.
     * So not only will any auto-generated route have the parent route prefixes,
     * the front end should expect to load the PageTemplate/PageData nested within
     * the parent page(s) up to the point where a parent is defined as not nested or
     * does not have a parent route
     * E.g. the parent route's page may just be a Hero and some Tab navigation.
     */
    public bool $isNested = true;

    public string $title = 'Unnamed Page';

    public ?string $metaDescription;
}
