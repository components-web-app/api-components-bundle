<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Publishable;

use Doctrine\Common\Annotations\Reader;
use Silverback\ApiComponentBundle\Annotation\Publishable;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableHelper
{
    use ClassMetadataTrait;

    private Reader $reader;
    private AuthorizationCheckerInterface $authorizationChecker;
    private string $permission;

    public function __construct(Reader $reader, AuthorizationCheckerInterface $authorizationChecker, string $permission)
    {
        $this->reader = $reader;
        $this->authorizationChecker = $authorizationChecker;
        $this->permission = $permission;
    }

    public function isGranted(): bool
    {
        return $this->authorizationChecker->isGranted(new Expression($this->permission));
    }

    /**
     * @param object|string $class
     */
    public function isPublishable($class): bool
    {
        return null !== $this->getConfiguration($class);
    }

    /**
     * @param object|string $class
     */
    public function getConfiguration($class): ?Publishable
    {
        /* @var Publishable|null $configuration */
        return $this->reader->getClassAnnotation(new \ReflectionClass($class), Publishable::class);
    }
}
