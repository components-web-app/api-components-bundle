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

namespace Silverback\ApiComponentBundle\Helper;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Annotation\Publishable;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableHelper extends AbstractHelper
{
    private AuthorizationCheckerInterface $authorizationChecker;
    private string $permission;

    public function __construct(Reader $reader, ManagerRegistry $registry, AuthorizationCheckerInterface $authorizationChecker, string $permission)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->permission = $permission;
        $this->initRegistry($registry);
        $this->initReader($reader);
    }

    public function isGranted(): bool
    {
        return $this->authorizationChecker->isGranted(new Expression($this->permission));
    }

    public function isActivePublishedAt(object $object): bool
    {
        if (!$this->isConfigured($object)) {
            throw new \InvalidArgumentException(sprintf('Object of class %s does not implement publishable configuration.', \get_class($object)));
        }

        $value = $this->getClassMetadata($object)->getFieldValue($object, $this->getConfiguration($object)->fieldName);

        return null !== $value && new \DateTimeImmutable() >= $value;
    }

    public function hasPublicationDate(object $object): bool
    {
        if (!$this->isConfigured($object)) {
            throw new \InvalidArgumentException(sprintf('Object of class %s does not implement publishable configuration.', \get_class($object)));
        }

        return null !== $this->getClassMetadata($object)->getFieldValue($object, $this->getConfiguration($object)->fieldName);
    }

    /**
     * @param object|string $class
     */
    public function getConfiguration($class): Publishable
    {
        return $this->getAnnotationConfiguration($class, Publishable::class);
    }

    public function isPublishedRequest(Request $request): bool
    {
        return $request->query->getBoolean('published', false);
    }
}
