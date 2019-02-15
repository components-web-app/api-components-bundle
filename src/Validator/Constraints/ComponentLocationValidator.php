<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\ComponentLocation as ComponentLocationEntity;
use Silverback\ApiComponentBundle\Entity\ValidComponentInterface;
use Silverback\ApiComponentBundle\Validator\ClassNameValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ComponentLocationValidator extends ConstraintValidator
{
    /**
     * @param mixed $entity
     * @param Constraint $constraint
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
            /** @var ArrayCollection $validComponents */
            $validComponents = $content->getValidComponents();
            $component = $entity->getComponent();
            $componentIsValid = $this->validateValidComponentInterface($component, $validComponents);
            if (!$componentIsValid) {
                $this->context->buildViolation($constraint->message)
                    ->atPath('component')
                    ->setParameter('{{ component }}', get_class($component))
                    ->setParameter('{{ string }}', implode(', ', $validComponents->toArray()))
                    ->setParameter('{{ content }}', get_class($content))
                    ->addViolation();
            }
        }
    }

    /**
     * @param null|AbstractComponent $component
     * @param ArrayCollection $validComponents
     * @return bool
     */
    private function validateValidComponentInterface(?AbstractComponent $component, ArrayCollection $validComponents): bool
    {
        if ($validComponents->count()) {
            $componentIsValid = false;
            foreach ($validComponents as $validComponent) {
                if ($componentIsValid = ClassNameValidator::isClassSame($validComponent, $component)) {
                    break;
                }
            }
            return $componentIsValid;
        }
        return true;
    }
}
