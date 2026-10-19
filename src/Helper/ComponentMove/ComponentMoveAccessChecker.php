<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\ComponentMove;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ComponentMoveAccessChecker
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly ResourceAccessCheckerInterface $resourceAccessChecker,
    ) {
    }

    /**
     * @param class-string $resourceClass
     */
    public function denyUnlessGranted(string $resourceClass, string $method, ?object $object): void
    {
        $this->denyUnlessExpressionGranted($resourceClass, $this->operation($resourceClass, $method)->getSecurity(), $object, $object);
    }

    /**
     * @param class-string $resourceClass
     */
    public function denyUnlessGrantedPostDenormalize(string $resourceClass, string $method, object $object, ?object $previousObject): void
    {
        $this->denyUnlessExpressionGranted($resourceClass, $this->operation($resourceClass, $method)->getSecurityPostDenormalize(), $object, $previousObject);
    }

    /**
     * @param class-string $resourceClass
     */
    private function denyUnlessExpressionGranted(string $resourceClass, ?string $expression, ?object $object, ?object $previousObject): void
    {
        if (null !== $expression && !$this->resourceAccessChecker->isGranted($resourceClass, $expression, ['object' => $object, 'previous_object' => $previousObject])) {
            throw new AccessDeniedException('Access Denied.');
        }
    }

    /**
     * @param class-string $resourceClass
     */
    private function operation(string $resourceClass, string $method): HttpOperation
    {
        foreach ($this->resourceMetadataFactory->create($resourceClass) as $resource) {
            foreach ($resource->getOperations() ?? [] as $operation) {
                if ($operation->getMethod() === $method && !$operation instanceof CollectionOperationInterface && (HttpOperation::METHOD_POST === $method) === !$operation->getUriVariables()) {
                    return $operation;
                }
            }
        }

        throw new AccessDeniedException(\sprintf('%s has no %s operation.', $resourceClass, $method));
    }
}
