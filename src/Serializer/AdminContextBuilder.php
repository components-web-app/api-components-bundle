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

namespace Silverback\ApiComponentBundle\Serializer;

use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

class AdminContextBuilder implements SerializerContextBuilderInterface
{
    private SerializerContextBuilderInterface $decorated;
    private AuthorizationCheckerInterface $authorizationChecker;
    private ClassMetadataFactoryInterface $classMetadataFactory;

    public function __construct(SerializerContextBuilderInterface $decorated, AuthorizationCheckerInterface $authorizationChecker, ClassMetadataFactoryInterface $classMetadataFactory)
    {
        $this->decorated = $decorated;
        $this->authorizationChecker = $authorizationChecker;
        $this->classMetadataFactory = $classMetadataFactory;
    }

    public function createFromRequest(Request $request, bool $normalization, array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $serializerGroupsConfigured = isset($context['groups']) && \is_array($context['groups']);
        if (!$serializerGroupsConfigured && $this->classHasSerializerGroupsOnProperties($context['resource_class'])) {
            $context['groups'] = [];
            $serializerGroupsConfigured = true;
        }

        if ($serializerGroupsConfigured) {
            $context['groups'] = $this->getAllSerializationGroups($context['groups'], $normalization);
        }

        return $context;
    }

    private function classHasSerializerGroupsOnProperties(string $resourceClass): bool
    {
        $serializerClassMetadata = $this->classMetadataFactory->getMetadataFor($resourceClass);
        $serializerAttributeMetadata = $serializerClassMetadata->getAttributesMetadata();
        foreach ($serializerAttributeMetadata as $metadata) {
            if (\count($metadata->groups)) {
                return true;
            }
        }

        return false;
    }

    private function getAllSerializationGroups(array $groups, bool $normalization): array
    {
        array_push($groups, ...$this->getSerializationGroups('default', $normalization));
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            array_push($groups, ...$this->getSerializationGroups('admin', $normalization));
        }
        if ($this->authorizationChecker->isGranted('ROLE_SUPER_ADMIN')) {
            array_push($groups, ...$this->getSerializationGroups('super_admin', $normalization));
        }

        return $groups;
    }

    private function getSerializationGroups(string $groupName, bool $normalization): array
    {
        return [$groupName, sprintf('%s:%s', $groupName, $normalization ? 'read' : 'write')];
    }
}
