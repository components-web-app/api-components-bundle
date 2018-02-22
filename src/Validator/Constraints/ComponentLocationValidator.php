<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Silverback\ApiComponentBundle\Entity\ValidComponentInterface;
use Silverback\ApiComponentBundle\Validator\ClassNameValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ComponentLocationValidator extends ConstraintValidator
{
    /**
     * @param \Silverback\ApiComponentBundle\Entity\Content\ComponentLocation $entity
     * @param Constraint $constraint
     * @throws \ReflectionException
     */
    public function validate($entity, Constraint $constraint): void
    {
        $content = $entity->getContent();
        if ($content instanceof ValidComponentInterface) {
            $validComponents = $content->getValidComponents();
            if ($validComponents->count()) {
                $componentIsValid = false;
                $component = $entity->getComponent();
                foreach ($validComponents as $validComponent) {
                    if (ClassNameValidator::isClassSame($validComponent, $component)) {
                        $componentIsValid = true;
                        break;
                    }
                }
                if (!$componentIsValid) {
                    $this->context->buildViolation($constraint->message)
                        ->atPath('component')
                        ->setParameter('{{ string }}', implode(', ', $validComponents->toArray()))
                        ->addViolation();
                }
            }
        }
    }
}
