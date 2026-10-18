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

use Doctrine\ORM\PersistentCollection;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentCycleDetector;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class AcyclicComponentGroupOwnersValidator extends ConstraintValidator
{
    public function __construct(private readonly ComponentCycleDetector $cycleDetector)
    {
    }

    /**
     * @param iterable<AbstractComponent>|null $value
     * @param AcyclicComponentGroupOwners      $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        $group = $this->context->getObject();
        if (!$group instanceof ComponentGroup) {
            return;
        }

        foreach ($value ?? [] as $owner) {
            if ($this->isNewOwner($owner, $group) && $this->cycleDetector->isOwnershipCyclic($owner, $group)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ reference }}', (string) $group->reference)
                    ->addViolation();

                return;
            }
        }
    }

    private function isNewOwner(AbstractComponent $owner, ComponentGroup $group): bool
    {
        $ownedGroups = $owner->getComponentGroups();
        if (!$ownedGroups instanceof PersistentCollection) {
            return true;
        }
        $ownedGroups->initialize();

        return !\in_array($group, $ownedGroups->getSnapshot(), true);
    }
}
