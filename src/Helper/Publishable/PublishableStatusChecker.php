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

namespace Silverback\ApiComponentsBundle\Helper\Publishable;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 * @author Daniel West <daniel@silverback.is>
 */
class PublishableStatusChecker
{
    use ClassMetadataTrait;

    private PublishableAttributeReader $annotationReader;
    private AuthorizationCheckerInterface $authorizationChecker;
    private string $permission;

    public function __construct(ManagerRegistry $registry, PublishableAttributeReader $annotationReader, AuthorizationCheckerInterface $authorizationChecker, string $permission)
    {
        $this->initRegistry($registry);
        $this->annotationReader = $annotationReader;
        $this->authorizationChecker = $authorizationChecker;
        $this->permission = $permission;
    }

    /**
     * @param object|string $class
     */
    public function isGranted($class): bool
    {
        try {
            return $this->authorizationChecker->isGranted(new Expression($this->annotationReader->getConfiguration($class)->isGranted ?? $this->permission));
        } catch (AuthenticationCredentialsNotFoundException $e) {
            return false;
        }
    }

    public function isActivePublishedAt(object $object): bool
    {
        if (!$this->annotationReader->isConfigured($object)) {
            throw new \InvalidArgumentException(sprintf('Object of class %s does not implement publishable configuration.', \get_class($object)));
        }

        $value = $this->getClassMetadata($object)->getFieldValue($object, $this->annotationReader->getConfiguration($object)->fieldName);

        return null !== $value && new \DateTimeImmutable() >= $value;
    }

    public function hasPublicationDate(object $object): bool
    {
        if (!$this->annotationReader->isConfigured($object)) {
            throw new \InvalidArgumentException(sprintf('Object of class %s does not implement publishable configuration.', \get_class($object)));
        }

        return null !== $this->getClassMetadata($object)->getFieldValue($object, $this->annotationReader->getConfiguration($object)->fieldName);
    }

    public function isPublishedRequest(Request $request): bool
    {
        return $request->query->getBoolean('published', false);
    }

    public function getAnnotationReader(): PublishableAttributeReader
    {
        return $this->annotationReader;
    }
}
