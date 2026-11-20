<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RoutePrefixResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class RoutePathStartValidator extends ConstraintValidator
{
    public function __construct(
        private readonly RoutePrefixResolver $routePrefixResolver,
        private readonly ManagerRegistry $registry,
    ) {
    }

    public function validate(mixed $route, Constraint $constraint): void
    {
        if (!$constraint instanceof RoutePathStart) {
            throw new UnexpectedTypeException($constraint, RoutePathStart::class);
        }

        if (!$route instanceof Route) {
            throw new UnexpectedTypeException($route, Route::class);
        }

        $routable = $route->getPage() ?? $route->getPageData();
        if (null === $routable || !$this->isWritten($route)) {
            return;
        }

        $start = $this->routePrefixResolver->requiredStart($routable);
        $path = $route->getPath();
        if (null === $start || (str_starts_with($path, $start) && $path !== $start)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath('path')
            ->setParameter('{{ start }}', $start)
            ->setInvalidValue($path)
            ->setCode(RoutePathStart::PATH_OUTSIDE_START_ERROR)
            ->addViolation();
    }

    private function isWritten(Route $route): bool
    {
        $manager = $this->registry->getManagerForClass(Route::class);
        if (!$manager instanceof EntityManagerInterface) {
            return true;
        }

        $original = $manager->getUnitOfWork()->getOriginalEntityData($route);

        return !$original
            || ($original['path'] ?? null) !== $route->getPath()
            || ($original['page'] ?? null) !== $route->getPage()
            || ($original['pageData'] ?? null) !== $route->getPageData();
    }
}
