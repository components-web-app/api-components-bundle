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
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

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

    private function classHasSerializerGroupsOnProperties(string $resourceClass): bool
    {
        $serializerClassMetadata = $this->classMetadataFactory->getMetadataFor($resourceClass);
        $serializerAttributeMetadata = $serializerClassMetadata->getAttributesMetadata();
        foreach ($serializerAttributeMetadata as $metadata) {
            if (count($metadata->groups)) {
                return true;
            }
        }
        return false;
    }

    public function createFromRequest(Request $request, bool $normalization, array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $serializerGroupsConfigured = isset($context['groups']) && is_array($context['groups']);
        if (!$serializerGroupsConfigured && $this->classHasSerializerGroupsOnProperties($context['resource_class'])) {
            $context['groups'] = [];
            $serializerGroupsConfigured = true;
        }

        if ($serializerGroupsConfigured) {
            array_push($context['groups'], ...$this->getSerializationGroups('default', $normalization));
            if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
                array_push($context['groups'], ...$this->getSerializationGroups('admin', $normalization));
            }
            if ($this->authorizationChecker->isGranted('ROLE_SUPER_ADMIN')) {
                array_push($context['groups'], ...$this->getSerializationGroups('super_admin', $normalization));
            }
        }

        return $context;
    }

    private function getSerializationGroups(string $groupName, bool $normalization): array
    {
        return [$groupName, sprintf('%s_%s', $groupName, $normalization ? 'read' : 'write')];
    }
}
