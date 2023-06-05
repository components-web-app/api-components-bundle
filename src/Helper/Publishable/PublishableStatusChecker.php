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

    private string $permission;

    public function __construct(
        ManagerRegistry $registry,
        private readonly PublishableAttributeReader $attributeReader,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        string $permission
    ) {
        $this->initRegistry($registry);
        $this->permission = $permission;
    }

    /**
     * @param object|string $class
     */
    public function isGranted($class): bool
    {
        try {
            return $this->authorizationChecker->isGranted(new Expression($this->attributeReader->getConfiguration($class)->isGranted ?? $this->permission));
        } catch (AuthenticationCredentialsNotFoundException $e) {
            return false;
        }
    }

    public function isActivePublishedAt(object $object): bool
    {
        if (!$this->attributeReader->isConfigured($object)) {
            throw new \InvalidArgumentException(sprintf('Object of class %s does not implement publishable configuration.', $object::class));
        }

        $value = $this->getClassMetadata($object)->getFieldValue($object, $this->attributeReader->getConfiguration($object)->fieldName);

        return null !== $value && new \DateTimeImmutable() >= $value;
    }

    public function hasPublicationDate(object $object): bool
    {
        if (!$this->attributeReader->isConfigured($object)) {
            throw new \InvalidArgumentException(sprintf('Object of class %s does not implement publishable configuration.', $object::class));
        }

        return null !== $this->getClassMetadata($object)->getFieldValue($object, $this->attributeReader->getConfiguration($object)->fieldName);
    }

    public function isRequestForPublished(Request $request): bool
    {
        return $request->query->getBoolean('published', false);
    }

    public function getAttributeReader(): PublishableAttributeReader
    {
        return $this->attributeReader;
    }
}
