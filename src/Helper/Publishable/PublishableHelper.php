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
use Silverback\ApiComponentsBundle\AnnotationReader\PublishableAnnotationReader;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 * @author Daniel West <daniel@silverback.is>
 */
class PublishableHelper
{
    use ClassMetadataTrait;

    private PublishableAnnotationReader $annotationReader;
    private AuthorizationCheckerInterface $authorizationChecker;
    private string $permission;

    public function __construct(ManagerRegistry $registry, PublishableAnnotationReader $annotationReader, AuthorizationCheckerInterface $authorizationChecker, string $permission)
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
        return $this->authorizationChecker->isGranted(new Expression($this->annotationReader->getConfiguration($class)->isGranted ?? $this->permission));
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

    public function getAnnotationReader(): PublishableAnnotationReader
    {
        return $this->annotationReader;
    }
}
