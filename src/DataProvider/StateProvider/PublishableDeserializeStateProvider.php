<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProvider\StateProvider;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProviderInterface<object>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class PublishableDeserializeStateProvider implements ProviderInterface
{
    use ClassMetadataTrait;

    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(
        private readonly ProviderInterface $decorated,
        private readonly PublishableStatusChecker $publishableStatusChecker,
        ManagerRegistry $registry,
    ) {
        $this->initRegistry($registry);
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        $request = $context['request'] ?? null;
        $previousData = $request instanceof Request ? $request->attributes->get('previous_data') : null;
        $attributeReader = $this->publishableStatusChecker->getAttributeReader();
        if (
            !\is_object($data)
            || !\is_object($previousData)
            || !$operation instanceof HttpOperation
            || !\in_array($operation->getMethod(), [HttpOperation::METHOD_PUT, HttpOperation::METHOD_PATCH], true)
            || !$attributeReader->isConfigured($data)
            || !$this->publishableStatusChecker->isRequestForPublished($request)
        ) {
            return $data;
        }

        $fieldName = $attributeReader->getConfiguration($data)->fieldName;
        if ($this->getClassMetadata($previousData)->getFieldValue($previousData, $fieldName) !== $this->getClassMetadata($data)->getFieldValue($data, $fieldName)) {
            throw new UnprocessableEntityHttpException('You cannot change the publication date of a published resource.');
        }

        return $data;
    }
}
