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

use Doctrine\ORM\Events as DoctrineEvents;
use GuzzleHttp\Client;
use Silverback\ApiComponentsBundle\DataTransformer\CollectionOutputDataTransformer;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PurgeHttpCacheListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\ScopingHttpClient;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ApiPlatformCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $itemsPerPageParameterName = $container->getParameter('api_platform.collection.pagination.items_per_page_parameter_name');

        $container->getDefinition(CollectionOutputDataTransformer::class)->setArgument('$itemsPerPageParameterName', $itemsPerPageParameterName);

        // Todo: revert to checking for service id api_platform.http_cache.purger when https://github.com/api-platform/core/pull/4695 is merged
        if ($container->hasDefinition('api_platform.http_cache.purger.varnish.xkey')) {
            // Todo: When above is merged, no need for this custom alias which is too specific.
            // TODO: @@@@!!!!! REMOVE GUZZLE CLIENT IN COMPOSER TOO
            $container
                ->setAlias('api_platform.http_cache.purger', 'api_platform.http_cache.purger.varnish.xkey');

            $container
                ->register('silverback.api_components.event_listener.doctrine.purge_http_cache_listener', PurgeHttpCacheListener::class)
                ->setArguments([
                    new Reference('api_platform.http_cache.purger'),
                    new Reference('api_platform.iri_converter'),
                ])
                ->addTag('doctrine.event_listener', ['event' => DoctrineEvents::onFlush]);

            // Todo: remove this - API Platform will be implementing this shortly using http client instead of guzzle
            $config = $container->getExtensionConfig('api_platform');
            $apiPlatformConfig = array_merge(...$config);
            $definitions = [];
            foreach ($apiPlatformConfig['http_cache']['invalidation']['varnish_urls'] as $key => $url) {
                $ops = $apiPlatformConfig['http_cache']['invalidation']['request_options'] ?? [];
                $definition = new Definition(ScopingHttpClient::class, [new Reference('http_client'), $url, ['base_uri' => $url] + $ops]);
                $definition->setFactory([ScopingHttpClient::class, 'forBaseUri']);

                $definitions[] = $definition;
            }
            $container->findDefinition('api_platform.http_cache.purger.varnish.xkey')->setArgument('$clients', $definitions);
        }
    }
}
