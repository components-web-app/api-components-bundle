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
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableContextBuilder implements SerializerContextBuilderInterface
{
    private SerializerContextBuilderInterface $decorated;
    private AuthorizationCheckerInterface $authorizationChecker;
    private ClassMetadataFactoryInterface $classMetadataFactory;
    private string $permission;

    public function __construct(SerializerContextBuilderInterface $decorated, AuthorizationCheckerInterface $authorizationChecker, ClassMetadataFactoryInterface $classMetadataFactory, string $permission)
    {
        $this->decorated = $decorated;
        $this->authorizationChecker = $authorizationChecker;
        $this->classMetadataFactory = $classMetadataFactory;
        $this->permission = $permission;
    }

    public function createFromRequest(Request $request, bool $normalization, array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $serializerGroupsConfigured = isset($context['groups']) && \is_array($context['groups']);
        if (!$serializerGroupsConfigured && $this->classHasSerializerGroupsOnProperties($context['resource_class'])) {
            $context['groups'] = [];
            $serializerGroupsConfigured = true;
        }

        if ($serializerGroupsConfigured && $this->authorizationChecker->isGranted(new Expression($this->permission))) {
            $context['groups'][] = sprintf('%s:publishable', $context['resource_class']);
        }

        return $context;
    }

    private function classHasSerializerGroupsOnProperties(string $resourceClass): bool
    {
        $serializerAttributeMetadata = $this->classMetadataFactory->getMetadataFor($resourceClass)->getAttributesMetadata();
        foreach ($serializerAttributeMetadata as $metadata) {
            if (\count($metadata->groups)) {
                return true;
            }
        }

        return false;
    }
}
