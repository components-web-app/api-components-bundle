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

use ApiPlatform\Metadata\Exception\ExceptionInterface;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Fixture\Builder\IriLink;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\PropertyValue;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\SiteSnapshot;

/**
 * @internal
 */
final class IriValueLinker
{
    /** @var array<string, array{string, string}>|null */
    private ?array $targets = null;

    public function __construct(
        private readonly SiteSnapshot $site,
        private readonly IriConverterInterface $iriConverter,
        private readonly PhpValueExporter $exporter,
        private readonly NotReproducedReport $report,
    ) {
    }

    /**
     * @return array{?PropertyValue, list<string>}
     */
    public function link(string $className, PropertyValue $value): array
    {
        $calls = [];
        $linked = $this->walk($value->value, [], $className, $value->property, $calls);
        if ([] === $calls) {
            return [$value, []];
        }
        if (null === $linked) {
            return [null, $calls];
        }

        return [new PropertyValue($value->kind, $value->property, $linked, $value->writable, $value->setter), $calls];
    }

    /**
     * @param list<string|int> $key
     * @param list<string>     $calls
     */
    private function walk(mixed $value, array $key, string $className, string $property, array &$calls): mixed
    {
        if (\is_array($value)) {
            foreach ($value as $index => $item) {
                $value[$index] = $this->walk($item, [...$key, $index], $className, $property, $calls);
            }

            return $value;
        }
        if (!\is_string($value) || !preg_match('#^/[^\s?\#]+$#', $value)) {
            return $value;
        }
        $target = $this->target($value);
        if (null !== $target) {
            $calls[] = $this->call($property, $key, ...$target);

            return null;
        }
        if ($this->isItemIri($value)) {
            $path = $property . implode('', array_map(static fn (string|int $segment): string => '[' . json_encode($segment) . ']', $key));
            $this->report->add(\sprintf('%s::$%s holds the IRI "%s", which is not a page, layout, routed page data or route of the generated site, so it is copied as it is.', $className, $path, $value));
        }

        return $value;
    }

    /**
     * @param list<string|int> $key
     */
    private function call(string $property, array $key, string $target, string $name): string
    {
        $arguments = [$this->exporter->export($property)];
        if ([] !== $key) {
            $arguments[] = 'key: ' . $this->exporter->export(1 === \count($key) ? $key[0] : $key);
        }
        $arguments[] = $target . ': ' . $this->exporter->export($name);

        return 'linkIri(' . implode(', ', $arguments) . ')';
    }

    /**
     * @return array{string, string}|null
     */
    private function target(string $iri): ?array
    {
        $targets = $this->targets ??= $this->index();

        return $targets[$iri] ?? $targets[self::withoutFirstSegment($iri) ?? ''] ?? null;
    }

    private function isItemIri(string $iri): bool
    {
        foreach (array_filter([$iri, self::withoutFirstSegment($iri)]) as $candidate) {
            try {
                $this->iriConverter->getResourceFromIri($candidate);

                return true;
            } catch (ItemNotFoundException) {
                return true;
            } catch (ExceptionInterface) {
            }
        }

        return false;
    }

    /**
     * @return array<string, array{string, string}>
     */
    private function index(): array
    {
        $targets = [];
        foreach ($this->site->layouts as $layout) {
            $this->register($targets, $layout, [IriLink::LAYOUT, (string) $layout->reference]);
        }
        $queue = [...$this->site->pages, ...$this->site->pageData];
        while (null !== ($page = array_shift($queue))) {
            $this->registerPage($targets, $page);
            array_push($queue, ...$this->site->childrenOf($page));
        }
        foreach ([...$this->site->routes, ...$this->site->redirects] as $route) {
            $this->register($targets, $route, [IriLink::ROUTE, $route->getName()]);
        }

        return $targets;
    }

    /**
     * @param array<string, array{string, string}> $targets
     */
    private function registerPage(array &$targets, AbstractPage $page): void
    {
        if ($page instanceof Page) {
            $this->register($targets, $page, [IriLink::PAGE, (string) $page->reference]);
        } elseif ($page instanceof AbstractPageData && null !== $page->getRoute()) {
            $this->register($targets, $page, [IriLink::PAGE_DATA, $page->getRoute()->getName()]);
        }
    }

    /**
     * @param array<string, array{string, string}> $targets
     * @param array{string, string}                $target
     */
    private function register(array &$targets, object $entity, array $target): void
    {
        try {
            $iri = $this->iriConverter->getIriFromResource($entity);
        } catch (ExceptionInterface) {
            $iri = null;
        }
        if (null === $iri) {
            return;
        }
        $targets[$iri] ??= $target;
        $unprefixed = self::withoutFirstSegment($iri);
        if (null !== $unprefixed) {
            $targets[$unprefixed] ??= $target;
        }
    }

    private static function withoutFirstSegment(string $iri): ?string
    {
        $second = strpos($iri, '/', 1);

        return false === $second || $second === \strlen($iri) - 1 ? null : substr($iri, $second);
    }
}
