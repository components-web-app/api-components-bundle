<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Silverback\ApiComponentBundle\Entity\SortableInterface;
use Silverback\ApiComponentBundle\Repository\ComponentLocationRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Silverback\ApiComponentBundle\Entity\Component\ComponentLocation as ComponentLocationEntity;

/**
 * THIS WAS CREATED TO SORT ABSTRACT PAGES - IT DOES NOT DO THAT AND APPEARS REDUNDANT - NEEDS CHECKING AND REMOVING
 * Class SortableValidator
 * @package Silverback\ApiComponentBundle\Validator\Constraints
 */
class SortableValidator extends ConstraintValidator
{
    private $componentLocationRepository;

    public function __construct(ComponentLocationRepository $componentLocationRepository)
    {
        $this->componentLocationRepository = $componentLocationRepository;
    }

    public function validate($sortable, Constraint $constraint): void
    {
        if (!$constraint instanceof Sortable) {
            throw new UnexpectedTypeException($constraint, Sortable::class);
        }

        if (!$sortable instanceof SortableInterface) {
            throw new UnexpectedTypeException($sortable, SortableInterface::class);
        }

        if ($sortable->getSort() === null) {
            $collection = $sortable->getSortCollection();
            if (
                $collection === null &&
                $sortable instanceof ComponentLocationEntity &&
                ($dynamicPageClass = $sortable->getDynamicPageClass())
            ) {
                $collection = $this->componentLocationRepository->findBy([
                    'dynamicPageClass' => $dynamicPageClass
                ]);
            }
            $sortable->setSort($sortable->calculateSort(true, $collection));
        }

        if ($sortable->getSort() === null) {
            $this->context->buildViolation($constraint->message)
                ->atPath('sort')
                ->addViolation();
        }
    }
}
