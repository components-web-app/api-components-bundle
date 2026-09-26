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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<object>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class ComponentUsageStateProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(
        private ProviderInterface $decorated,
        private ComponentUsageMetadataFactory $metadataFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        if (!$data instanceof ComponentInterface || !str_ends_with((string) $operation->getName(), '_get_usage')) {
            return $data;
        }

        $usage = $this->metadataFactory->create($data);
        $request = $context['request'] ?? null;
        if ($request instanceof Request) {
            $request->attributes->set('data', $usage);
        }

        return $usage;
    }
}
