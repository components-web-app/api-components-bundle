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

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\VariableNamer;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\EntityStateReader;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\SiteSnapshot;
use Silverback\ApiComponentsBundle\Fixture\Generator\StoredFileExport;

/**
 * @internal
 */
final readonly class ScaffoldPlanner
{
    public function __construct(
        private ManagerRegistry $registry,
        private IriConverterInterface $iriConverter,
        private EntityStateReader $stateReader,
        private StoredFileExport $storedFileExporter,
        private ImportCollector $imports,
        private PhpValueExporter $exporter,
        private \DateTimeImmutable $now,
        private bool $keepTimestamps = false,
    ) {
    }

    public function plan(SiteSnapshot $site): ScaffoldPlan
    {
        $report = new NotReproducedReport($this->iriConverter);
        $namer = new VariableNamer();

        $methods = (new MethodPlanner($site, $namer, $this->stateReader, $this->imports, $this->exporter, $report, $this->now, $this->keepTimestamps))->plan();
        $scopes = new ComponentScopeDecider($methods);
        $order = new ExecutionOrder($methods);

        $groupPlanner = new GroupPlanner($this->registry, $this->iriConverter, $this->imports, $this->exporter, $report);
        $values = new PropertyValueWriter($this->stateReader, $this->storedFileExporter, $this->imports, $this->exporter, $report);
        $placer = new ComponentPlacer($site, $this->stateReader, $values, new IriValueLinker($site, $this->iriConverter, $this->exporter, $report), $groupPlanner, $this->imports, $this->exporter, $report, $this->now, $this->keepTimestamps);
        foreach ($order->methods as $method) {
            $placer->placeIn($method);
        }
        $placer->describeAll();

        $relatedPairs = array_map(static fn (array $relation): array => [$relation[0], $relation[2]], $placer->relations());
        $scopes->decide($placer->plans(), [...$relatedPairs, ...$placer->draftPairs()]);

        $declarations = new DeclarationOrderer($order, $placer->plans(), $placer->relations());
        $declarations->declare();
        $declarations->linkDrafts($placer->draftPairs());

        return new ScaffoldPlan($methods, $this->nameComponents($methods, $namer), $this->imports, $report->all());
    }

    /**
     * @param list<MethodPlan> $methods
     *
     * @return list<ComponentPlan>
     */
    private function nameComponents(array $methods, VariableNamer $namer): array
    {
        $properties = [];
        foreach (ScaffoldPlan::inFileOrder($methods) as $method) {
            foreach ($method->components as $component) {
                if (null === $component->draft) {
                    $component->name = $namer->variable($component->class, ...$component->phrases);
                } else {
                    [$component->name, $component->draft->name] = $namer->variableWithDraft($component->class, ...$component->phrases);
                }
                foreach ([$component, $component->draft] as $named) {
                    if (null !== $named && $named->property) {
                        $properties[] = $named;
                    }
                }
            }
        }

        return $properties;
    }
}
