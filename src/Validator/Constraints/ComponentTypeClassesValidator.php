<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\ComponentInterface;
use Silverback\ApiComponentBundle\Validator\ClassNameValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

class ComponentTypeClassesValidator extends ConstraintValidator
{
    private $components;

    public function __construct(
        iterable $components
    ) {
        $this->components = $components;
    }

    /**
     * @param mixed $values
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
        /** @var Collection $value */
        foreach ($values as $value)
        {
            try {
                $valid = ClassNameValidator::validate($value, $this->components);
                if (!$valid) {
                    $conditionsStr = vsprintf(' They should all extend %s, implement %s or be tagged %s', [
                        Component::class,
                        ComponentInterface::class,
                        'silverback_api_component.component'
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
