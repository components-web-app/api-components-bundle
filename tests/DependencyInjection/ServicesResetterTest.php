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
 * @author Daniel West <daniel@silverback.is>
 */
class ServicesResetterTest extends TestCase
{
    private const MUST_BE_RESETTABLE = [
        'services.php' => [
            'silverback.api_components.data_collector.data',
            'silverback.security.jwt_event_listener',
            'silverback.api_components.event_listener.console.console_output',
            'silverback.api_components.fixture.cwa_fixture_builder',
            'silverback.api_components.doctrine.event_listener.uploadable_file_deletion',
        ],
        'services_doctrine_orm_mercure_publisher.php' => ['silverback.api_components.mercure.resource_publisher'],
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
