<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Validator\Constraints;

use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\AttributeReader\ExplicitAllowOnlyAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentCycleDetector;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionIriResolver;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ListedComponentPositions;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Metadata\Provider\PageDataMetadataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;
use Silverback\ApiComponentsBundle\Validator\Constraints\ComponentPosition as ComponentPositionConstraint;
use Silverback\ApiComponentsBundle\Validator\Constraints\ComponentPositionValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ComponentPositionValidator>
 */
class ComponentPositionValidatorTest extends ConstraintValidatorTestCase
{
    private const IRIS = [
        DummyComponent::class => '/component/dummy_components',
        RestrictedComponent::class => '/component/restricted_components',
    ];

    /** @var array<string, object> */
    private array $resources = [];

    private ?\WeakMap $originalData = null;

    private bool $withEntityManager = true;

    protected function createValidator(): ConstraintValidatorInterface
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(
            static fn (object|string $resource, int $referenceType = 1, ?Operation $operation = null): string => self::IRIS[\is_string($resource) ? $resource : $resource::class],
        );
        $iriConverter->method('getResourceFromIri')->willReturnCallback(
            fn (string $iri): object => $this->resources[$iri] ?? throw new ItemNotFoundException('Item not found'),
        );

        return new ComponentPositionValidator(
            $iriConverter,
            new PageDataMetadataProvider($this->createStub(ResourceNameCollectionFactoryInterface::class), $this->createStub(PageDataMetadataFactoryInterface::class)),
            new ExplicitAllowOnlyAttributeReader($this->createStub(ManagerRegistry::class)),
            new ListedComponentPositions($iriConverter),
            new ComponentPositionIriResolver($iriConverter),
            new ComponentCycleDetector($this->createStub(ManagerRegistry::class), new PublishableAttributeReader($this->createStub(ManagerRegistry::class))),
            $this->registry(),
        );
    }

    private function registry(): ManagerRegistry
    {
        $this->originalData = new \WeakMap();
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturnCallback(fn (object $entity): array => $this->originalData[$entity] ?? []);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(fn (string $class): ?EntityManagerInterface => $this->withEntityManager && ComponentPosition::class === $class ? $entityManager : null);

        return $registry;
    }

    public function test_a_new_position_that_places_a_component_inside_itself_is_refused_on_component(): void
    {
        $container = new DummyComponent();
        $group = $this->groupHolding(0);
        $group->addComponent($container);
        $position = $this->newPosition($group);
        $position->component = $container;

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->buildViolation('The component cannot be placed in the component group `{{ reference }}`: the group is inside the component.')
            ->atPath('property.path.component')
            ->setParameter('{{ reference }}', 'header')
            ->assertRaised();
    }

    public function test_a_cycle_in_a_group_with_no_reference_is_refused(): void
    {
        $container = new DummyComponent();
        $group = new ComponentGroup();
        $group->addComponent($container);
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $position->component = $container;

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->buildViolation('The component cannot be placed in the component group `{{ reference }}`: the group is inside the component.')
            ->atPath('property.path.component')
            ->setParameter('{{ reference }}', '')
            ->assertRaised();
    }

    public function test_a_position_is_checked_for_a_cycle_without_an_entity_manager(): void
    {
        $this->withEntityManager = false;
        $container = new DummyComponent();
        $group = $this->groupHolding(0);
        $group->addComponent($container);
        $position = $this->newPosition($group);
        $position->component = $container;

        $this->validator->validate($position, new ComponentPositionConstraint());

        self::assertCount(1, $this->context->getViolations());
    }

    public function test_a_position_that_places_a_component_elsewhere_is_allowed(): void
    {
        $group = $this->groupHolding(0);
        $group->addComponent(new DummyComponent());

        $this->validator->validate($this->newPosition($group), new ComponentPositionConstraint());

        $this->assertNoViolation();
    }

    public function test_an_unchanged_position_in_a_cycle_already_held_is_not_checked(): void
    {
        $container = new DummyComponent();
        $group = $this->groupHolding(0);
        $group->addComponent($container);
        $position = $this->newPosition($group);
        $position->component = $container;
        $group->addComponentPosition($position);
        $this->originalData[$position] = ['component' => $container, 'componentGroup' => $group];

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function changedFields(): iterable
    {
        yield 'component' => ['component'];
        yield 'componentGroup' => ['componentGroup'];
    }

    #[DataProvider('changedFields')]
    public function test_a_position_whose_component_or_group_changed_is_checked(string $changed): void
    {
        $container = new DummyComponent();
        $group = $this->groupHolding(0);
        $group->addComponent($container);
        $position = $this->newPosition($group);
        $position->component = $container;
        $group->addComponentPosition($position);
        $original = ['component' => $container, 'componentGroup' => $group];
        $original[$changed] = 'component' === $changed ? new DummyComponent() : new ComponentGroup();
        $this->originalData[$position] = $original;

        $this->validator->validate($position, new ComponentPositionConstraint());

        self::assertCount(1, $this->context->getViolations());
    }

    public function test_an_anchor_in_the_group_is_allowed(): void
    {
        $group = $this->groupHolding(1);
        $this->resources['/a'] = $group->componentPositions->first();
        $position = $this->newPosition($group);
        $position->after = '/a';

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->assertNoViolation();
    }

    public function test_a_before_anchor_in_the_group_is_allowed(): void
    {
        $group = $this->groupHolding(1);
        $this->resources['/a'] = $group->componentPositions->first();
        $position = $this->newPosition($group);
        $position->before = '/a';

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function anchors(): iterable
    {
        yield 'after' => ['after'];
        yield 'before' => ['before'];
    }

    #[DataProvider('anchors')]
    public function test_an_anchor_in_another_group_is_refused(string $anchor): void
    {
        $group = $this->groupHolding(0);
        $this->resources['/x'] = $this->groupHolding(1)->componentPositions->first();
        $position = $this->newPosition($group);
        $position->{$anchor} = '/x';

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->buildViolation('The position `{{ iri }}` is not in the component group `{{ reference }}`.')
            ->atPath('property.path.' . $anchor)
            ->setParameter('{{ iri }}', '/x')
            ->setParameter('{{ reference }}', 'header')
            ->assertRaised();
    }

    #[DataProvider('anchors')]
    public function test_an_anchor_that_does_not_exist_is_refused(string $anchor): void
    {
        $position = $this->newPosition($this->groupHolding(1));
        $position->{$anchor} = '/deleted';

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->buildViolation('The position `{{ iri }}` is not in the component group `{{ reference }}`.')
            ->atPath('property.path.' . $anchor)
            ->setParameter('{{ iri }}', '/deleted')
            ->setParameter('{{ reference }}', 'header')
            ->assertRaised();
    }

    public function test_an_anchor_that_is_not_a_position_is_refused(): void
    {
        $position = $this->newPosition($this->groupHolding(1));
        $this->resources['/component'] = new DummyComponent();
        $position->after = '/component';

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->assertSame(1, $this->context->getViolations()->count());
        $this->assertSame('property.path.after', $this->context->getViolations()->get(0)->getPropertyPath());
    }

    #[DataProvider('anchors')]
    public function test_an_anchor_with_a_sort_value_is_refused(string $anchor): void
    {
        $group = $this->groupHolding(1);
        $this->resources['/a'] = $group->componentPositions->first();
        $position = $this->newPosition($group);
        $position->{$anchor} = '/a';
        $position->markSortValueSent();

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->buildViolation('Send an anchor or a sortValue, not both: the server chooses the sortValue.')
            ->atPath('property.path.' . $anchor)
            ->assertRaised();
    }

    public function test_a_sort_value_with_no_anchor_is_ignored(): void
    {
        $position = $this->newPosition($this->groupHolding(1));
        $position->markSortValueSent();

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->assertNoViolation();
    }

    public function test_both_anchors_are_refused_on_before(): void
    {
        $group = $this->groupHolding(2);
        $this->resources['/a'] = $group->componentPositions->first();
        $this->resources['/b'] = $group->componentPositions->last();
        $position = $this->newPosition($group);
        $position->after = '/a';
        $position->before = '/b';

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->buildViolation('Send either after or before, not both.')
            ->atPath('property.path.before')
            ->assertRaised();
    }

    public function test_a_position_cannot_be_its_own_anchor(): void
    {
        $group = $this->groupHolding(1);
        $position = $group->componentPositions->first();
        $this->resources['/a'] = $position;
        $position->after = '/a';

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->buildViolation('A position cannot be placed relative to itself.')
            ->atPath('property.path.after')
            ->assertRaised();
    }

    public function test_an_anchor_is_checked_with_the_group_full(): void
    {
        $group = $this->groupHolding(1);
        $group->maxComponents = 1;
        $this->resources['/a'] = $group->componentPositions->first();
        $position = $this->newPosition($group);
        $position->after = '/a';

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->assertSame(1, $this->context->getViolations()->count());
        $this->assertSame('property.path.componentGroup', $this->context->getViolations()->get(0)->getPropertyPath());
    }

    public function test_a_position_with_no_group_is_not_checked(): void
    {
        $position = new ComponentPosition();
        $position->component = new DummyComponent();

        $this->validator->validate($position, new ComponentPositionConstraint());

        $this->assertNoViolation();
    }

    public function test_a_group_with_no_maximum_takes_any_number_of_components(): void
    {
        $group = $this->groupHolding(3);

        $this->validator->validate($this->newPosition($group), new ComponentPositionConstraint());

        $this->assertNoViolation();
    }

    public function test_a_position_added_to_a_full_group_is_refused_naming_the_limit(): void
    {
        $group = $this->groupHolding(2);
        $group->maxComponents = 2;

        $this->validator->validate($this->newPosition($group), new ComponentPositionConstraint());

        $this->buildViolation('The component group `{{ reference }}` is full: it holds at most {{ limit }} component.|The component group `{{ reference }}` is full: it holds at most {{ limit }} components.')
            ->atPath('property.path.componentGroup')
            ->setParameter('{{ reference }}', 'header')
            ->setParameter('{{ limit }}', '2')
            ->setPlural(2)
            ->assertRaised();
    }

    public function test_a_position_added_to_a_group_below_its_maximum_is_allowed(): void
    {
        $group = $this->groupHolding(1);
        $group->maxComponents = 2;

        $this->validator->validate($this->newPosition($group), new ComponentPositionConstraint());

        $this->assertNoViolation();
    }

    public function test_a_position_already_in_a_group_over_its_maximum_is_allowed(): void
    {
        $group = $this->groupHolding(3);
        $group->maxComponents = 1;

        $this->validator->validate($group->componentPositions->last(), new ComponentPositionConstraint());

        $this->assertNoViolation();
    }

    public function test_a_dynamic_position_counts_towards_the_maximum(): void
    {
        $group = $this->groupHolding(0);
        $dynamic = new ComponentPosition();
        $dynamic->componentGroup = $group;
        $dynamic->pageDataProperty = 'component';
        $group->addComponentPosition($dynamic);
        $group->maxComponents = 1;

        $this->validator->validate($this->newPosition($group), new ComponentPositionConstraint());

        $this->assertSame(1, $this->context->getViolations()->count());
    }

    public function test_a_position_hidden_by_the_allowed_components_does_not_count_towards_the_maximum(): void
    {
        $group = $this->groupHolding(1);
        $this->place($group, new RestrictedComponent());
        $group->allowedComponents = ['/component/dummy_components'];
        $group->maxComponents = 2;

        $this->validator->validate($this->newPosition($group), new ComponentPositionConstraint());

        $this->assertNoViolation();
    }

    private function groupHolding(int $count): ComponentGroup
    {
        $group = new ComponentGroup();
        $group->reference = 'header';
        for ($i = 0; $i < $count; ++$i) {
            $this->place($group, new DummyComponent());
        }

        return $group;
    }

    private function place(ComponentGroup $group, AbstractComponent $component): void
    {
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $position->component = $component;
        $group->addComponentPosition($position);
    }

    private function newPosition(ComponentGroup $group): ComponentPosition
    {
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $position->component = new DummyComponent();

        return $position;
    }
}
