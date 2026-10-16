<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator\Plan;

use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\VariableNamer;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\EntityStateReader;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\SiteSnapshot;
use Silverback\ApiComponentsBundle\Utility\RouteName;

/**
 * @internal
 */
final readonly class MethodPlanner
{
    public function __construct(
        private SiteSnapshot $site,
        private VariableNamer $namer,
        private EntityStateReader $stateReader,
        private ImportCollector $imports,
        private PhpValueExporter $exporter,
        private NotReproducedReport $report,
        private \DateTimeImmutable $now,
        private bool $keepTimestamps,
    ) {
    }

    /**
     * @return list<MethodPlan>
     */
    public function plan(): array
    {
        $methods = [];
        foreach ($this->site->layouts as $layout) {
            $methods[] = $this->layoutMethod($layout);
        }
        foreach ($this->site->pages as $page) {
            $methods[] = $this->pageMethod($page);
        }
        foreach ($this->site->pageData as $pageData) {
            $methods[] = $this->pageDataMethod($pageData);
        }
        if ([] !== $this->site->redirects) {
            $methods[] = $this->redirectsMethod();
        }
        foreach ($this->site->unresolvedRedirects as $redirect) {
            $this->report->add(\sprintf('The redirect from "%s" to "%s", whose target has no page.', $redirect->getPath(), $redirect->getRedirect()?->getPath()));
        }
        foreach ($this->site->unreached as $page) {
            $this->report->add(\sprintf('%s is nested under a page that is itself nested under it, so it has no top-level page to be built from.', $this->report->describe($page)));
        }

        return $methods;
    }

    private function layoutMethod(Layout $layout): MethodPlan
    {
        $method = new MethodPlan(MethodKind::Layout, $this->namer->method(MethodKind::Layout->value, (string) $layout->reference), $layout);
        $method->arguments = [$this->export($layout->reference), 'uiComponent: ' . $this->export($this->stripPrefix($layout->uiComponent, 'CwaLayout'))];
        if (null !== $layout->uiClassNames) {
            $method->arguments[] = 'uiClassNames: ' . $this->export($layout->uiClassNames);
        }

        return $method;
    }

    private function pageMethod(Page $page): MethodPlan
    {
        $method = new MethodPlan(MethodKind::Page, $this->namer->method(MethodKind::Page->value, (string) $page->reference), $page);
        $method->arguments = [
            $this->export($page->reference),
            'uiComponent: ' . $this->export($this->stripPrefix($page->uiComponent, 'CwaPage')),
            'layout: ' . $this->export($page->layout->reference ?? ''),
            ...$this->routeArguments($page->getRoute()),
        ];
        if ($page->isTemplate) {
            $method->arguments[] = 'isTemplate: true';
        }
        if (null !== $page->uiClassNames) {
            $method->arguments[] = 'uiClassNames: ' . $this->export($page->uiClassNames);
        }

        if (null !== $page->getTitle()) {
            $method->calls[] = 'title(' . $this->export($page->getTitle()) . ')';
        }
        if (null !== $page->getMetaDescription()) {
            $method->calls[] = 'metaDescription(' . $this->export($page->getMetaDescription()) . ')';
        }
        if (null === $page->getRoute() && !$page->isTemplate) {
            $method->calls[] = 'withoutRoute()';
        }
        if ($page->isReachableWithoutRoute) {
            $method->calls[] = 'reachableWithoutRoute()';
        }
        $this->addLiveAt($method, $page->getRoute());
        $this->addChildren($method, $page);

        return $method;
    }

    private function pageDataMethod(AbstractPageData $pageData): MethodPlan
    {
        $method = new MethodPlan(MethodKind::PageData, $this->namer->method(MethodKind::PageData->value, (string) $pageData->getTitle()), $pageData);
        $method->entityClass = $this->imports->add($this->stateReader->className($pageData));
        $method->arguments = ['$pageData'];
        $template = $pageData->page ?? null;
        if (null !== $template) {
            $method->arguments[] = 'template: ' . $this->export($template->reference);
        }
        array_push($method->arguments, ...$this->routeArguments($pageData->getRoute()));

        if (null === $pageData->getRoute()) {
            $method->calls[] = 'withoutRoute()';
        }
        $this->addLiveAt($method, $pageData->getRoute());
        $this->addChildren($method, $pageData);

        return $method;
    }

    private function redirectsMethod(): MethodPlan
    {
        $method = new MethodPlan(MethodKind::Redirects, $this->namer->method(MethodKind::Redirects->value));
        $taken = [];
        foreach ($this->site->routes as $route) {
            $taken[$route->getName()] = true;
        }

        foreach ($this->site->redirects as $redirect) {
            $arguments = [$this->export($redirect->getPath()), 'to: ' . $this->export($redirect->getRedirect()?->getName())];
            $derived = RouteName::fromPath($redirect->getPath());
            for ($suffix = 1, $unique = $derived; isset($taken[$unique]); ++$suffix) {
                $unique = RouteName::withSuffix($derived, $suffix);
            }
            if ($unique !== $redirect->getName()) {
                $arguments[] = 'name: ' . $this->export($redirect->getName());
            }
            $taken[$redirect->getName()] = true;
            $method->redirects[] = $arguments;
            $liveAt = $redirect->getLiveAt();
            if (null === $liveAt || $liveAt > $this->now) {
                $this->report->add(\sprintf('The redirect from "%s" goes live at %s, but a fixture redirect is always live.', $redirect->getPath(), null === $liveAt ? 'no date (a draft)' : $liveAt->format(\DATE_ATOM)));
            }
        }

        return $method;
    }

    /**
     * @return list<string>
     */
    private function routeArguments(?Route $route): array
    {
        if (null === $route) {
            return [];
        }
        $arguments = ['route: ' . $this->export($route->getPath())];
        if (RouteName::fromPath($route->getPath()) !== $route->getName()) {
            $arguments[] = 'routeName: ' . $this->export($route->getName());
        }

        return $arguments;
    }

    private function addLiveAt(MethodPlan $method, ?Route $route): void
    {
        if (null === $route) {
            return;
        }
        $liveAt = $route->getLiveAt();
        if (null === $liveAt || $this->keepTimestamps || $liveAt > $this->now) {
            $method->calls[] = 'liveAt(' . $this->export(null === $liveAt ? null : \DateTimeImmutable::createFromInterface($liveAt)) . ')';
        }
    }

    private function addChildren(MethodPlan $method, AbstractPage $parent): void
    {
        foreach ($this->site->childrenOf($parent) as $child) {
            if ($child instanceof Page) {
                $method->children[] = $this->pageMethod($child);
            } elseif ($child instanceof AbstractPageData) {
                $method->children[] = $this->pageDataMethod($child);
            }
        }
    }

    private function export(mixed $value): string
    {
        return $this->exporter->export($value, $this->imports) ?? 'null';
    }

    private function stripPrefix(?string $value, string $prefix): string
    {
        if (null === $value) {
            return '';
        }

        return str_starts_with($value, $prefix) ? substr($value, \strlen($prefix)) : $value;
    }
}
