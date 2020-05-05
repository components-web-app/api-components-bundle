<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Collection\CollectionHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ResourceIriValidator extends ConstraintValidator
{
    private CollectionHelper $converter;

    public function __construct(CollectionHelper $converter)
    {
        $this->converter = $converter;
    }

    /**
     * @param ResourceIri $constraint
     */
    public function validate($iri, Constraint $constraint): void
    {
        try {
            $this->converter->getRouterParametersFromIri((string) $iri);
        } catch (InvalidArgumentException $e) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $iri ?? 'null')
                ->addViolation();
        }
    }
}
