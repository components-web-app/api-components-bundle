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
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Annotation\Publishable;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Utility\ClassMetadataTrait;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
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

    public function __construct(Reader $reader, ManagerRegistry $registry, AuthorizationCheckerInterface $authorizationChecker, string $permission)
    {
        $this->reader = $reader;
        $this->authorizationChecker = $authorizationChecker;
        $this->permission = $permission;
        $this->initRegistry($registry);
    }

    public function isGranted(): bool
    {
        return $this->authorizationChecker->isGranted(new Expression($this->permission));
    }

    public function isActivePublishedAt(object $object): bool
    {
        if (!$this->isPublishable($object)) {
            throw new \InvalidArgumentException(sprintf('Object of class %s does not implement publishable configuration.', \get_class($object)));
        }

        $value = $this->getClassMetadata($object)->getFieldValue($object, $this->getConfiguration($object)->fieldName);

        return null !== $value && new \DateTimeImmutable() >= $value;
    }

    public function hasPublicationDate(object $object): bool
    {
        if (!$this->isPublishable($object)) {
            throw new \InvalidArgumentException(sprintf('Object of class %s does not implement publishable configuration.', \get_class($object)));
        }

        return null !== $this->getClassMetadata($object)->getFieldValue($object, $this->getConfiguration($object)->fieldName);
    }

    /**
     * @param object|string $class
     */
    public function isPublishable($class): bool
    {
        try {
            $this->getConfiguration($class);
        } catch (InvalidArgumentException $exception) {
            return false;
        }

        return true;
    }

    /**
     * @param object|string $class
     */
    public function getConfiguration($class): Publishable
    {
        $configuration = null;
        if (\is_string($class) || \is_object($class)) {
            $configuration = $this->reader->getClassAnnotation(new \ReflectionClass($class), Publishable::class);
        }

        if (!$configuration || !$configuration instanceof Publishable) {
            $className = \is_string($class) ? $class : \get_class($class);
            throw new InvalidArgumentException(sprintf('Could not get publishable configuration for %s', $className));
        }

        return $configuration;
    }

    public function isPublishedRequest(Request $request): bool
    {
        return $request->query->getBoolean('published', false);
    }
}
