<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass;

use ApiPlatform\Symfony\Bundle\DependencyInjection\Configuration as ApiPlatformConfiguration;
use Silverback\ApiComponentsBundle\EventListener\Api\CollectionApiEventListener;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ApiPlatformCompilerPass implements CompilerPassInterface
{
    private const string HTTP_CACHE_FLUSHER = 'silverback.api_components.http_cache.flusher';
    private const string INVALIDATION_CLIENT_PREFIX = 'api_platform.invalidation_http_client.';
    private const string EXCEPTION_TO_STATUS = 'api_platform.exception_to_status';

    public function process(ContainerBuilder $container): void
    {
        $itemsPerPageParameterName = $container->getParameter('api_platform.collection.pagination.items_per_page_parameter_name');

        $container->findDefinition(CollectionApiEventListener::class)->setArgument('$itemsPerPageParameterName', $itemsPerPageParameterName);

        if ($container->hasAlias('api_platform.http_cache.purger')) {
            // we have implemented fully custom logic
            $container->removeDefinition('api_platform.doctrine.listener.http_cache.purge');
        } else {
            $container->removeDefinition('silverback.api_components.http_cache.purger');
        }

        $this->configureHttpCacheFlusher($container);

        $this->appendApiPlatformDefaultExceptionStatuses($container);

        $apiPlatformMercurePublishListener = 'api_platform.doctrine.orm.listener.mercure.publish';
        if ($container->hasDefinition($apiPlatformMercurePublishListener)) {
            // we have implemented fully custom logic
            $container->removeDefinition($apiPlatformMercurePublishListener);
        }
    }

    private function appendApiPlatformDefaultExceptionStatuses(ContainerBuilder $container): void
    {
        /** @var ArrayNode $tree */
        $tree = (new ApiPlatformConfiguration())->getConfigTreeBuilder()->buildTree();
        $defaults = $tree->getChildren()['exception_to_status']->getDefaultValue();

        $container->setParameter(self::EXCEPTION_TO_STATUS, $container->getParameter(self::EXCEPTION_TO_STATUS) + $defaults);
    }

    private function configureHttpCacheFlusher(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::HTTP_CACHE_FLUSHER)) {
            return;
        }

        $invalidationClients = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, self::INVALIDATION_CLIENT_PREFIX)) {
                continue;
            }
            $invalidationClients[] = ['url' => $definition->getArgument(1), 'client' => new Reference($id)];
        }

        $container->getDefinition(self::HTTP_CACHE_FLUSHER)->setArgument('$invalidationClients', $invalidationClients);
    }
}
