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
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class RoutePathCharactersValidator extends ConstraintValidator
{
    private const string FORBIDDEN_CHARACTERS = '/[\s?#]/u';

    public function __construct(private readonly ?ManagerRegistry $registry = null)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RoutePathCharacters) {
            throw new UnexpectedTypeException($constraint, RoutePathCharacters::class);
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (1 !== preg_match(self::FORBIDDEN_CHARACTERS, $value) || $this->isStoredPath($value)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setInvalidValue($value)
            ->setCode(RoutePathCharacters::FORBIDDEN_CHARACTER_ERROR)
            ->addViolation();
    }

    private function isStoredPath(string $path): bool
    {
        $route = $this->context->getObject();
        if (!$route instanceof Route) {
            return false;
        }

        $manager = $this->registry?->getManagerForClass(Route::class);
        if (!$manager instanceof EntityManagerInterface) {
            return false;
        }

        return ($manager->getUnitOfWork()->getOriginalEntityData($route)['path'] ?? null) === $path;
    }
}
