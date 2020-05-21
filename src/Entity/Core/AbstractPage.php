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

    protected ?Route $route = null;

    /**
     * This will be se so that when auto-generating a route for a newly created
     * Page / PageData, we can prepend parent routes.
     */
    protected ?Route $parentRoute = null;

    /**
     * If true, then the Page/PageData is nested within the parentRoute.
     * So not only will any auto-generated route have the parent route prefixes,
     * the front end should expect to load the Page/PageData nested within
     * the parent page(s) up to the point where a parent is defined as not nested or
     * does not have a parent route
     * E.g. the parent route's page may just be a Hero and some Tab navigation.
     */
    protected bool $nested = true;

    protected string $title = 'Unnamed Page';

    protected ?string $metaDescription = null;

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function setRoute(?Route $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function getParentRoute(): ?Route
    {
        return $this->parentRoute;
    }

    public function setParentRoute(?Route $parentRoute): self
    {
        $this->parentRoute = $parentRoute;

        return $this;
    }

    public function isNested(): bool
    {
        return $this->nested;
    }

    public function setNested(bool $nested): self
    {
        $this->nested = $nested;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): self
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }
}
