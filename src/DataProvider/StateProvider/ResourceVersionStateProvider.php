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
use Silverback\ApiComponentsBundle\Entity\Utility\VersionedInterface;
use Silverback\ApiComponentsBundle\Exception\ResourceVersionConflictException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<object>
 */
final readonly class ResourceVersionStateProvider implements ProviderInterface
{
    private const array CHECKED_METHODS = [HttpOperation::METHOD_PATCH, HttpOperation::METHOD_PUT, HttpOperation::METHOD_DELETE];

    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(private ProviderInterface $decorated)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        $request = $context['request'] ?? null;
        if (
            !$data instanceof VersionedInterface
            || !$request instanceof Request
            || !$operation instanceof HttpOperation
            || !\in_array($operation->getMethod(), self::CHECKED_METHODS, true)
            || !$request->headers->has('If-Match')
        ) {
            return $data;
        }

        if (isset($uriVariables['id']) && (string) $uriVariables['id'] !== (string) $data->getId()) {
            throw new ResourceVersionConflictException();
        }

        if (!self::matches((string) $request->headers->get('If-Match'), $data->getResourceVersion())) {
            throw new ResourceVersionConflictException();
        }

        return $data;
    }

    private static function matches(string $ifMatch, int $version): bool
    {
        foreach (explode(',', $ifMatch) as $entityTag) {
            if (\in_array(trim($entityTag), ['*', '"' . $version . '"'], true)) {
                return true;
            }
        }

        return false;
    }
}
