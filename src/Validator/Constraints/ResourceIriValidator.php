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
use Silverback\ApiComponentsBundle\Utility\ApiResourceRouteFinder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ResourceIriValidator extends ConstraintValidator
{
    private ApiResourceRouteFinder $resourceRouteFinder;

    public function __construct(ApiResourceRouteFinder $resourceRouteFinder)
    {
        $this->resourceRouteFinder = $resourceRouteFinder;
    }

    /**
     * @param ResourceIri $constraint
     */
    public function validate($iri, Constraint $constraint): void
    {
        try {
            $this->resourceRouteFinder->findByIri((string) $iri);
        } catch (InvalidArgumentException $e) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $iri ?? 'null')
                ->addViolation();
        }
    }
}
