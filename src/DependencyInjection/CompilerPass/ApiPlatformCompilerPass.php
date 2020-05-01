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

namespace Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass;

use Silverback\ApiComponentsBundle\DataTransformer\CollectionOutputDataTransformer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ApiPlatformCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $itemsPerPageParameterName = $container->getParameter('api_platform.collection.pagination.items_per_page_parameter_name');

        $container->getDefinition(CollectionOutputDataTransformer::class)->setArgument('$itemsPerPageParameterName', $itemsPerPageParameterName);
    }
}
