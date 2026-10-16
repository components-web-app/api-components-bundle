<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition as ComponentPositionEntity;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class AddedComponentPositionsValidator extends ConstraintValidator
{
    /**
     * @param iterable<ComponentPositionEntity>|null $value
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        foreach ($value ?? [] as $index => $position) {
            $group = $position->componentGroup;
            if (!$group || $group->componentPositions->contains($position)) {
                continue;
            }

            $this->context->getValidator()
                ->inContext($this->context)
                ->atPath(\sprintf('[%s]', $index))
                ->validate($position, [new ComponentPosition()]);
        }
    }
}
