<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\ComponentInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

class ComponentTypeClassesValidator extends ConstraintValidator
{
    /**
     * @param Collection $values
     * @param Constraint $constraint
     * @throws \ReflectionException
     */
    public function validate($values, Constraint $constraint): void
    {
        if (!$values instanceof Collection) {
            $this->context
                ->buildViolation('The value should be an instance of ' . Collection::class)
                ->addViolation()
            ;
            return;
        }

        foreach ($values as $value) {
            try {
                $refl = new \ReflectionClass($value);
                $valid = \in_array(ComponentInterface::class, $refl->getInterfaceNames(), true);
                if (!$valid) {
                    $conditionsStr = vsprintf('. They should all extend %s or just implement %s', [
                        AbstractComponent::class,
                        ComponentInterface::class
                    ]);
                    $this->context
                        ->buildViolation($constraint->message . $conditionsStr)
                        ->setParameter('{{ string }}', $value)
                        ->addViolation()
                    ;
                    break;
                }
            } catch (InvalidArgumentException $exception) {
                $this->context
                    ->buildViolation($constraint->message . ' ' . $exception->getMessage())
                    ->setParameter('{{ string }}', $value)
                    ->addViolation()
                ;
                break;
            }
        }
    }
}
