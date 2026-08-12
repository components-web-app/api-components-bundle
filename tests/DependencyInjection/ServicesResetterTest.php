<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Under a long-running runtime — FrankenPHP worker mode, RoadRunner — one kernel serves many
 * requests, so a shared service holding per-request state carries it into the next request unless
 * the framework resets it. That is not a theoretical risk here: a marker left on
 * UploadableFileManager between requests silently deleted uploaded files on publish.
 *
 * `ResetInterface` alone does nothing — a service is only reset if it carries the `kernel.reset`
 * tag. Autoconfiguration adds that tag, but this is a bundle: applications may disable
 * autoconfiguration, and several of the bundle's own definitions already opt out of it. So the tag
 * must be on the definition itself, which is what this asserts — the service definitions are read
 * straight from the config files rather than from a booted kernel, where autoconfiguration would
 * mask a missing tag.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class ServicesResetterTest extends TestCase
{
    /**
     * Config file => service ids defined in it that accumulate state during a request and must be
     * emptied between requests.
     *
     * Add here whenever a bundle service gains mutable per-request state — or, better, scope the
     * state so it cannot outlive its request (UploadableFileManager keys its markers in a WeakMap by
     * the resource they belong to, so it needs no reset at all).
     */
    private const MUST_BE_RESETTABLE = [
        'services.php' => [
            // Profiler panel data gathered across the request
            'silverback.api_components.data_collector.data',
            // Holds the JWT to be written as a cookie on the response
            'silverback.security.jwt_event_listener',
        ],
        // Queues objects changed during the request, flushed on propagate()
        'services_doctrine_orm_mercure_publisher.php' => ['silverback.api_components.mercure.resource_publisher'],
        // Same, for cache invalidation
        'services_doctrine_orm_http_cache_purger.php' => ['silverback.api_components.http_cache.purger'],
    ];

    public function test_bundle_services_holding_request_state_are_tagged_kernel_reset(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config'));

        foreach (self::MUST_BE_RESETTABLE as $file => $serviceIds) {
            $loader->load($file);

            foreach ($serviceIds as $serviceId) {
                self::assertTrue(
                    $container->hasDefinition($serviceId),
                    \sprintf('"%s" is not defined in %s — has it been renamed?', $serviceId, $file)
                );
                self::assertTrue(
                    $container->getDefinition($serviceId)->hasTag('kernel.reset'),
                    \sprintf('"%s" holds per-request state but is not tagged kernel.reset, so it would carry that state into the next request in worker mode.', $serviceId)
                );
            }
        }
    }
}
