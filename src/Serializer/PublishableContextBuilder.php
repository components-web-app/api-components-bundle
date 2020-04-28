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

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableContextBuilder implements SerializerContextBuilderInterface
{
    private SerializerContextBuilderInterface $decorated;
    private PublishableHelper $publishableHelper;

    public function __construct(SerializerContextBuilderInterface $decorated, PublishableHelper $publishableHelper)
    {
        $this->decorated = $decorated;
        $this->publishableHelper = $publishableHelper;
    }

    public function createFromRequest(Request $request, bool $normalization, array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);
        if (empty($resourceClass = $context['resource_class']) || empty($context['groups'])) {
            return $context;
        }

        $reflectionClass = new \ReflectionClass($resourceClass);
        if ($normalization) {
            $context['groups'][] = sprintf('%s:publishable:read', $reflectionClass->getShortName());
        } elseif ($this->publishableHelper->isGranted()) {
            $context['groups'][] = sprintf('%s:publishable:write', $reflectionClass->getShortName());
        }

        return $context;
    }
}
