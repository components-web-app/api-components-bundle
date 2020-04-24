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
use Silverback\ApiComponentBundle\Publishable\PublishableHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableContextBuilder implements SerializerContextBuilderInterface
{
    private SerializerContextBuilderInterface $decorated;
    private PublishableHelper $publishableHelper;
    private ClassMetadataFactoryInterface $classMetadataFactory;

    public function __construct(SerializerContextBuilderInterface $decorated, PublishableHelper $publishableHelper, ClassMetadataFactoryInterface $classMetadataFactory)
    {
        $this->decorated = $decorated;
        $this->publishableHelper = $publishableHelper;
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

        if ($serializerGroupsConfigured && $this->publishableHelper->isGranted()) {
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
