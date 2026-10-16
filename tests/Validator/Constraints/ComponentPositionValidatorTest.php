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

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\ExplicitAllowOnlyAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
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

    protected function createValidator(): ConstraintValidatorInterface
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(
            static fn (object|string $resource, int $referenceType = 1, ?Operation $operation = null): string => self::IRIS[\is_string($resource) ? $resource : $resource::class],
        );

        return new ComponentPositionValidator(
            $iriConverter,
            new PageDataMetadataProvider($this->createStub(ResourceNameCollectionFactoryInterface::class), $this->createStub(PageDataMetadataFactoryInterface::class)),
            new ExplicitAllowOnlyAttributeReader($this->createStub(ManagerRegistry::class)),
            new ListedComponentPositions($iriConverter),
        );
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
