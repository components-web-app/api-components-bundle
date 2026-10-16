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

use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Validator\Constraints\AddedComponentPositions;
use Silverback\ApiComponentsBundle\Validator\Constraints\AddedComponentPositionsValidator;
use Silverback\ApiComponentsBundle\Validator\Constraints\ComponentPosition as ComponentPositionConstraint;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<AddedComponentPositionsValidator>
 */
class AddedComponentPositionsValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new AddedComponentPositionsValidator();
    }

    public function test_a_position_added_to_a_group_is_validated_at_its_index(): void
    {
        $group = new ComponentGroup();
        $kept = $this->position($group);
        $group->addComponentPosition($kept);
        $added = $this->position($group);

        $this->expectValidateValueAt(0, '[1]', $added, [new ComponentPositionConstraint()]);

        $this->validator->validate([$kept, $added], new AddedComponentPositions());

        $this->assertNoViolation();
    }

    public function test_a_position_with_no_group_is_not_validated(): void
    {
        $this->validator->validate([new ComponentPosition()], new AddedComponentPositions());

        $this->assertNoViolation();
    }

    public function test_no_positions_validates_nothing(): void
    {
        $this->validator->validate(null, new AddedComponentPositions());

        $this->assertNoViolation();
    }

    private function position(ComponentGroup $group): ComponentPosition
    {
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $position->component = new DummyComponent();

        return $position;
    }
}
