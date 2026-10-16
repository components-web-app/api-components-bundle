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

use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

class PageBuilder
{
    /** @var array<string, GroupBuilder> */
    private array $groupBuilders = [];
    /** @var list<\Closure> */
    private array $nestedClosures = [];
    private bool $hasLiveAt = false;
    private bool $withoutRoute = false;
    private ?string $redirectTo = null;
    private ?\DateTimeImmutable $liveAt = null;
    private ?Page $existingPage = null;

    public function __construct(private readonly Page $page)
    {
    }

    public function nested(\Closure $configure): self
    {
        $this->nestedClosures[] = $configure;

        return $this;
    }

    /** @return list<\Closure> */
    public function getNestedClosures(): array
    {
        return $this->nestedClosures;
    }

    public function title(string $title): self
    {
        $this->page->setTitle($title);

        return $this;
    }

    public function metaDescription(string $metaDescription): self
    {
        $this->page->setMetaDescription($metaDescription);

        return $this;
    }

    public function uiClassNames(string ...$classes): self
    {
        $this->page->uiClassNames = $classes;

        return $this;
    }

    public function reachableWithoutRoute(bool $reachable = true): self
    {
        $this->page->isReachableWithoutRoute = $reachable;

        return $this;
    }

    public function liveAt(?\DateTimeImmutable $liveAt): self
    {
        $this->hasLiveAt = true;
        $this->liveAt = $liveAt;

        return $this;
    }

    public function hasLiveAt(): bool
    {
        return $this->hasLiveAt;
    }

    public function getLiveAt(): ?\DateTimeImmutable
    {
        return $this->liveAt;
    }

    public function withoutRoute(): self
    {
        $this->withoutRoute = true;

        return $this;
    }

    public function isWithoutRoute(): bool
    {
        return $this->withoutRoute;
    }

    public function redirectTo(string $routeName): self
    {
        $this->redirectTo = $routeName;

        return $this;
    }

    /**
     * @internal
     */
    public function takeRedirectTo(): ?string
    {
        $routeName = $this->redirectTo;
        $this->redirectTo = null;

        return $routeName;
    }

    public function group(string $name, ?\Closure $configure = null, ?string $locationReference = null, array $allow = [], ?int $max = null): GroupBuilder
    {
        if (!isset($this->groupBuilders[$name])) {
            $this->groupBuilders[$name] = new GroupBuilder($name, $allow, $locationReference, $max);
        }
        if (null !== $configure) {
            $configure($this->groupBuilders[$name]);
        }

        return $this->groupBuilders[$name];
    }

    public function getPage(): Page
    {
        return $this->page;
    }

    public function getRoute(): ?Route
    {
        return ($this->existingPage ?? $this->page)->getRoute();
    }

    /**
     * @internal
     */
    public function setExistingPage(Page $page): void
    {
        $this->existingPage = $page;
    }

    /** @return array<string, GroupBuilder> */
    public function getGroupBuilders(): array
    {
        return $this->groupBuilders;
    }
}
