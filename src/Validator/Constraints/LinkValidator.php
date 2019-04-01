<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Silverback\ApiComponentBundle\Repository\Route\RouteRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\UrlValidator;

/**
 * @author Daniel West <daniel@silverback.is>
 * @Annotation()
 */
class LinkValidator extends UrlValidator
{
    /** @var RouteRepository */
    private $routeRepository;

    public function __construct(
        RouteRepository $routeRepository
    ) {
        $this->routeRepository = $routeRepository;
    }

    public function validate($value, Constraint $constraint)
    {
        $route = $this->routeRepository->find($value);
        if ($route) {
            return;
        }
        parent::validate($value, $constraint);
    }
}
