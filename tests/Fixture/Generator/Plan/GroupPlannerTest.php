<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator\Plan;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\IriConverterInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\GroupPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\GroupPlanner;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\NotReproducedReport;
use Silverback\ApiComponentsBundle\Tests\Fixture\Generator\GeneratorSiteTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyNavigationLink;

class GroupPlannerTest extends TestCase
{
    use GeneratorSiteTrait;

    private NotReproducedReport $report;
    private ImportCollector $imports;

    public function test_groups_are_sorted_by_reference(): void
    {
        $groups = [];
        foreach (['top', 'bottom', 'middle'] as $reference) {
            $group = new ComponentGroup();
            $group->reference = $reference;
            $groups[] = $group;
        }

        self::assertSame([$groups[1], $groups[2], $groups[0]], GroupPlanner::sort(new \ArrayIterator($groups)));
    }

    public function test_a_group_located_at_its_owner_is_named_without_a_location_reference(): void
    {
        $plan = $this->plan($this->group('top_/_/layouts/main', '/_/layouts/main'), $this->layout('main', 'CwaLayoutPrimary'));

        self::assertNotNull($plan);
        self::assertSame('top', $plan->name);
        self::assertSame(["'top'"], $plan->arguments);
        self::assertSame([], $this->report->all());
    }

    public function test_a_group_located_at_another_owner_is_reported_and_left_out(): void
    {
        $page = $this->page('home', null);

        self::assertNull($this->plan($this->group('top_/_/layouts/main', '/_/layouts/main'), $page));
        self::assertSame(['Component group "top_/_/layouts/main" is also used by /_/pages/home, which the fixture builder cannot share it with.'], $this->report->all());
    }

    public function test_a_group_name_and_location_reference_with_a_newline_are_written_escaped(): void
    {
        $plan = $this->plan($this->group("top\nline_site\nwide", '/_/layouts/main'), $this->layout('main', 'CwaLayoutPrimary'));

        self::assertNotNull($plan);
        self::assertSame("top\nline", $plan->name);
        self::assertSame(['"top\\nline"', 'locationReference: "site\\nwide"'], $plan->arguments);
    }

    public function test_a_reference_that_is_only_the_location_suffix_is_split_at_its_first_underscore(): void
    {
        $plan = $this->plan($this->group('_/_/layouts/main', '/_/layouts/main'), $this->layout('main', 'CwaLayoutPrimary'));

        self::assertSame(["''", "locationReference: '/_/layouts/main'"], $plan?->arguments);
    }

    public function test_a_group_with_no_location_is_split_at_its_first_underscore(): void
    {
        $plan = $this->plan($this->group('top_', null), $this->layout('main', 'CwaLayoutPrimary'));

        self::assertSame(["'top'", "locationReference: ''"], $plan?->arguments);
        self::assertSame([], $this->report->all());
    }

    public function test_a_reference_with_no_underscore_is_reported_and_left_out(): void
    {
        self::assertNull($this->plan($this->group('orphan', null), $this->layout('main', 'CwaLayoutPrimary')));
        self::assertSame(['Component group "orphan" on /_/layouts/main has a reference the fixture builder cannot express.'], $this->report->all());
    }

    public function test_allowed_components_are_written_as_imported_concrete_component_classes(): void
    {
        $this->blankNodeClasses = [DummyNavigationLink::class];
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main');
        $group->allowedComponents = ['/component/dummy_components', '/component/abstract_components', '/component/pages', '/component/dummy_navigation_links'];

        $plan = $this->plan($group, $this->layout('main', 'CwaLayoutPrimary'));

        self::assertSame(["'top'", 'allow: [DummyComponent::class]'], $plan?->arguments);
        self::assertContains('use ' . DummyComponent::class . ';', $this->imports->lines());
        self::assertSame([
            'Allowed component "/component/abstract_components" on the component group "top_/_/layouts/main" does not match a component class.',
            'Allowed component "/component/pages" on the component group "top_/_/layouts/main" does not match a component class.',
            'Allowed component "/component/dummy_navigation_links" on the component group "top_/_/layouts/main" does not match a component class.',
        ], $this->report->all());
    }

    public function test_a_maximum_number_of_components_is_written_after_the_allowed_components(): void
    {
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main');
        $group->allowedComponents = ['/component/dummy_components'];
        $group->maxComponents = 1;

        $plan = $this->plan($group, $this->layout('main', 'CwaLayoutPrimary'));

        self::assertSame(["'top'", 'allow: [DummyComponent::class]', 'max: 1'], $plan?->arguments);
        self::assertSame([], $this->report->all());
    }

    public function test_a_component_class_without_a_collection_iri_matches_nothing(): void
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(static function (object|string $resource): ?string {
            if (DummyComponent::class === $resource) {
                throw new InvalidArgumentException('No collection operation.');
            }

            return \is_string($resource) ? null : '/_/layouts/main';
        });
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main');
        $group->allowedComponents = ['/component/dummy_components'];

        $plan = $this->plan($group, $this->layout('main', 'CwaLayoutPrimary'), $iriConverter);

        self::assertSame(["'top'"], $plan?->arguments);
        self::assertCount(1, $this->report->all());
    }

    private function plan(ComponentGroup $group, Layout|Page $owner, ?IriConverterInterface $iriConverter = null): ?GroupPlan
    {
        $iriConverter ??= $this->iriConverter();
        $this->report = new NotReproducedReport($iriConverter);
        $this->imports = new ImportCollector('SiteScaffold');

        return (new GroupPlanner($this->registry(), $iriConverter, $this->imports, new PhpValueExporter(), $this->report))->plan($group, $owner);
    }

    private function group(string $reference, ?string $location): ComponentGroup
    {
        $group = new ComponentGroup();
        $group->reference = $reference;
        $group->location = $location;

        return $group;
    }
}
