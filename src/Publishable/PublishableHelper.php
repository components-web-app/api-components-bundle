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
use Silverback\ApiComponentBundle\Entity\Utility\PublishableInterface;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Utility\ClassMetadataTrait;
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

        if ($object instanceof PublishableInterface) {
            return $object->isPublished();
        }

        $value = $this->getClassMetadata($object)->getFieldValue($object, $this->getConfiguration($object)->fieldName);

        return null !== $value && new \DateTimeImmutable() >= $value;
    }

    public function hasPublicationDate(object $object): bool
    {
        if (!$this->isPublishable($object)) {
            throw new \InvalidArgumentException(sprintf('Object of class %s does not implement publishable configuration.', \get_class($object)));
        }

        if ($object instanceof PublishableInterface) {
            return $object->isPublished();
        }

        return null !== $this->getClassMetadata($object)->getFieldValue($object, $this->getConfiguration($object)->fieldName);
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
        if (null === $class || (\is_string($class) && !class_exists($class))) {
            throw new InvalidArgumentException(sprintf('$class passed to %s must be a valid class FQN or object', __CLASS__));
        }

        return $this->reader->getClassAnnotation(new \ReflectionClass($class), Publishable::class);
    }
}
