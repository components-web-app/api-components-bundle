<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Entity\ValidComponentInterface;
use Silverback\ApiComponentBundle\Validator\ClassNameValidator;
use Silverback\ApiComponentBundle\Entity\Content\ComponentLocation as ComponentLocationEntity;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ComponentLocationValidator extends ConstraintValidator
{
    /**
     * @param mixed $entity
     * @param Constraint $constraint
     * @throws \ReflectionException
     */
    public function validate($entity, Constraint $constraint): void
    {
        if (!$entity instanceof ComponentLocationEntity) {
            throw new \InvalidArgumentException(
                sprintf('The ComponentLocationValidator should only be used with %s', ComponentLocationEntity::class)
            );
        }
        $content = $entity->getContent();
        if ($content instanceof ValidComponentInterface) {
            /** @var ArrayCollection|string[] $validComponents */
            $validComponents = $content->getValidComponents();
            $componentIsValid = $this->validateValidComponentInterface($entity, $validComponents);
            if (!$componentIsValid) {
                $this->context->buildViolation($constraint->message)
                    ->atPath('component')
                    ->setParameter('{{ string }}', implode(', ', $validComponents->toArray()))
                    ->addViolation();
            }
        }
    }

    /**
     * @param ComponentLocationEntity $entity
     * @param ArrayCollection $validComponents
     * @return bool
     * @throws \ReflectionException
     */
    private function validateValidComponentInterface(ComponentLocationEntity $entity, ArrayCollection $validComponents)
    {
        $componentIsValid = false;
        if ($validComponents->count()) {
            $component = $entity->getComponent();
            foreach ($validComponents as $validComponent) {
                if (ClassNameValidator::isClassSame($validComponent, $component)) {
                    $componentIsValid = true;
                    break;
                }
            }
        }
        return $componentIsValid;
    }
}
