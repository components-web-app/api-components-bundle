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
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;

/**
 * @internal
 */
final class GroupPlanner
{
    /** @var array<string, string>|null */
    private ?array $componentClassesByIri = null;

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly IriConverterInterface $iriConverter,
        private readonly ImportCollector $imports,
        private readonly PhpValueExporter $exporter,
        private readonly NotReproducedReport $report,
    ) {
    }

    /**
     * @param iterable<ComponentGroup> $groups
     *
     * @return list<ComponentGroup>
     */
    public static function sort(iterable $groups): array
    {
        $groups = [...$groups];
        usort($groups, static fn (ComponentGroup $a, ComponentGroup $b): int => strcmp((string) $a->reference, (string) $b->reference));

        return $groups;
    }

    public function plan(ComponentGroup $group, Layout|Page|AbstractComponent $owner): ?GroupPlan
    {
        $reference = (string) $group->reference;
        $ownerIri = $this->report->describe($owner);
        $locationSuffix = '_' . $group->location;

        $locationReference = null;
        if (null !== $group->location && str_ends_with($reference, $locationSuffix) && \strlen($reference) > \strlen($locationSuffix)) {
            if ($group->location !== $ownerIri) {
                $this->report->add(\sprintf('Component group "%s" is also used by %s, which the fixture builder cannot share it with.', $reference, $ownerIri));

                return null;
            }
            $name = substr($reference, 0, -\strlen($locationSuffix));
        } elseif (str_contains($reference, '_')) {
            [$name, $locationReference] = explode('_', $reference, 2);
        } else {
            $this->report->add(\sprintf('Component group "%s" on %s has a reference the fixture builder cannot express.', $reference, $ownerIri));

            return null;
        }

        $arguments = [$this->export($name)];
        if (null !== $locationReference) {
            $arguments[] = 'locationReference: ' . $this->export($locationReference);
        }
        $allowed = $this->resolveAllowedClasses($group);
        if ([] !== $allowed) {
            $arguments[] = 'allow: [' . implode(', ', $allowed) . ']';
        }

        return new GroupPlan($name, $arguments);
    }

    /**
     * @return list<string>
     */
    private function resolveAllowedClasses(ComponentGroup $group): array
    {
        $allowed = [];
        foreach ($group->allowedComponents ?? [] as $iri) {
            $class = $this->getComponentClassesByIri()[$iri] ?? null;
            if (null === $class) {
                $this->report->add(\sprintf('Allowed component "%s" on the component group "%s" does not match a component class.', $iri, $group->reference));
                continue;
            }
            $allowed[] = $this->imports->add($class) . '::class';
        }

        return $allowed;
    }

    /**
     * @return array<string, string>
     */
    private function getComponentClassesByIri(): array
    {
        if (null !== $this->componentClassesByIri) {
            return $this->componentClassesByIri;
        }

        $this->componentClassesByIri = [];
        $manager = $this->registry->getManagerForClass(AbstractComponent::class);
        foreach ($manager?->getMetadataFactory()->getAllMetadata() ?? [] as $metadata) {
            $class = $metadata->getName();
            if (!is_a($class, AbstractComponent::class, true) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            try {
                $iri = $this->iriConverter->getIriFromResource($class, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($class));
            } catch (ExceptionInterface) {
                continue;
            }
            if (null !== $iri && !str_starts_with($iri, '/.well-known/genid/')) {
                $this->componentClassesByIri[$iri] = $class;
            }
        }

        return $this->componentClassesByIri;
    }

    private function export(mixed $value): string
    {
        return $this->exporter->export($value, $this->imports) ?? 'null';
    }
}
