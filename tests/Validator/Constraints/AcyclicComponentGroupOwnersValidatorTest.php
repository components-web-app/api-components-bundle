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

use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentCycleDetector;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;
use Silverback\ApiComponentsBundle\Validator\Constraints\AcyclicComponentGroupOwners;
use Silverback\ApiComponentsBundle\Validator\Constraints\AcyclicComponentGroupOwnersValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<AcyclicComponentGroupOwnersValidator>
 */
class AcyclicComponentGroupOwnersValidatorTest extends ConstraintValidatorTestCase
{
    use PublishableDatabaseTrait;

    protected function createValidator(): ConstraintValidatorInterface
    {
        $this->setUpPublishableDatabase();

        return new AcyclicComponentGroupOwnersValidator(new ComponentCycleDetector($this->registry, new PublishableAttributeReader($this->registry)));
    }

    public function test_a_new_owner_inside_the_group_is_refused(): void
    {
        $group = $this->group('outer');
        $inner = new DummyComponent();
        $this->place($group, $inner);
        $group->addComponent(new DummyComponent());
        $group->addComponent($inner);
        $this->setObject($group);

        $this->validator->validate($group->components, new AcyclicComponentGroupOwners());

        $this->buildViolation('The component group `{{ reference }}` cannot be owned by a component inside it.')
            ->setParameter('{{ reference }}', 'outer')
            ->assertRaised();
    }

    public function test_a_new_owner_outside_the_group_is_allowed(): void
    {
        $group = $this->group('outer');
        $group->addComponent(new DummyComponent());
        $this->setObject($group);

        $this->validator->validate($group->components, new AcyclicComponentGroupOwners());

        $this->assertNoViolation();
    }

    public function test_an_owner_already_held_in_a_cycle_is_not_checked_again(): void
    {
        $owner = new DummyComponent();
        $this->entityManager->persist($owner);
        $group = $this->group('loop');
        $group->addComponent($owner);
        $this->place($group, $owner);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $group = $this->entityManager->find(ComponentGroup::class, $group->getId());
        $this->setObject($group);

        $this->validator->validate($group->components, new AcyclicComponentGroupOwners());

        $this->assertNoViolation();
    }

    public function test_a_value_on_something_other_than_a_group_is_ignored(): void
    {
        $this->setObject(new DummyComponent());

        $this->validator->validate([new DummyComponent()], new AcyclicComponentGroupOwners());

        $this->assertNoViolation();
    }

    public function test_no_owners_is_allowed(): void
    {
        $this->setObject($this->group('empty'));

        $this->validator->validate(null, new AcyclicComponentGroupOwners());

        $this->assertNoViolation();
    }

    private function group(string $reference): ComponentGroup
    {
        $group = new ComponentGroup();
        $group->reference = $reference;
        $group->location = $reference;
        $group->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($group);

        return $group;
    }

    private function place(ComponentGroup $group, AbstractComponent $component): void
    {
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $position->sortValue = 0;
        $position->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $group->addComponentPosition($position);
        $component->addComponentPosition($position);
        $this->entityManager->persist($position);
    }
}
