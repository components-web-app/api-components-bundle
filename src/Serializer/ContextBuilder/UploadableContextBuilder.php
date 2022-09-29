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

namespace Silverback\ApiComponentsBundle\Serializer\ContextBuilder;

use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\UploadableLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class UploadableContextBuilder implements SerializerContextBuilderInterface
{
    private SerializerContextBuilderInterface $decorated;

    public function __construct(SerializerContextBuilderInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function createFromRequest(Request $request, bool $normalization, array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        if (empty($resourceClass = $context['resource_class']) || empty($context['groups']) || \in_array('Route:manifest:read', $context['groups'], true)) {
            return $context;
        }

        $reflectionClass = new \ReflectionClass($resourceClass);
        if ($normalization) {
            $context['groups'][] = sprintf('%s:%s:read', $reflectionClass->getShortName(), UploadableLoader::GROUP_NAME);
        } else {
            $context['groups'][] = sprintf('%s:%s:write', $reflectionClass->getShortName(), UploadableLoader::GROUP_NAME);
        }

        return $context;
    }
}
