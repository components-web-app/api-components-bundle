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

use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ResourceIriValidator extends ConstraintValidator
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * @param ResourceIri $constraint
     */
    public function validate($iri, Constraint $constraint): void
    {
        try {
            $parameters = $this->router->match($iri);
            if (!isset($parameters['_api_resource_class'])) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $iri)
                    ->addViolation();
            }
        } catch (RoutingExceptionInterface $e) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $iri)
                ->addViolation();
        }
    }
}
