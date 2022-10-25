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

use Silverback\ApiComponentsBundle\EventListener\Api\CollectionApiEventListener;
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

        $container->getDefinition(CollectionApiEventListener::class)->setArgument('$itemsPerPageParameterName', $itemsPerPageParameterName);
        $purgeListener = 'silverback.api_components.event_listener.doctrine.purge_http_cache_listener';

        if ($container->hasAlias('api_platform.http_cache.purger')) {
            // we have implemented fully custom logic
            $container->removeDefinition('api_platform.doctrine.listener.http_cache.purge');
        } else {
            $container->removeDefinition($purgeListener);
        }

        $publishListener = 'silverback.api_components.event_listener.doctrine.mercure_publish_listener';
        $apiPlatformMercurePublishListener = 'api_platform.doctrine.orm.listener.mercure.publish';
        if ($container->hasDefinition($apiPlatformMercurePublishListener)) {
            // we have implemented fully custom logic
            $container->removeDefinition($apiPlatformMercurePublishListener);
        } else {
            $container->removeDefinition($publishListener);
        }
    }
}
