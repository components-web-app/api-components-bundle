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

namespace Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Security\Voter\AbstractRoutableVoter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RoutableResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;

    public function __construct(ResourceMetadataFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);

        $refl = new \ReflectionClass($resourceClass);
        if (!$refl->implementsInterface(RoutableInterface::class)) {
            return $resourceMetadata;
        }

        $itemOps = $resourceMetadata->getItemOperations();
        foreach ($itemOps as $name => $config) {
            if ($config['security'] ?? null) {
                continue;
            }
            $itemOps[$name]['security'] = sprintf("is_granted('%s', object)", AbstractRoutableVoter::READ_ROUTABLE);
        }

        return $resourceMetadata->withItemOperations($itemOps);
    }
}
