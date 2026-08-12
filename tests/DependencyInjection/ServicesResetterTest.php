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

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

/**
 * Under a long-running runtime — FrankenPHP worker mode, RoadRunner — one kernel serves many
 * requests, so a shared service holding per-request state carries it into the next request unless
 * the framework resets it. That is not a theoretical risk here: a marker left on
 * UploadableFileManager between requests silently deleted uploaded files on publish.
 *
 * `ResetInterface` alone does nothing — a service is only reset if it carries the `kernel.reset`
 * tag. Autoconfiguration adds that tag, but this is a bundle: applications may disable
 * autoconfiguration, and several of the bundle's own definitions already opt out of it. So the tag
 * must be explicit on every service that holds request-scoped state.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class ServicesResetterTest extends KernelTestCase
{
    /**
     * Services that accumulate state during a request and must be emptied between requests.
     *
     * Add to this list whenever a bundle service gains mutable per-request state — or, better,
     * scope the state so it cannot outlive its request (UploadableFileManager keys its markers in a
     * WeakMap by the resource they belong to, so it needs no reset at all).
     */
    private const MUST_BE_RESETTABLE = [
        // Queues objects changed during the request, flushed on propagate()
        'silverback.api_components.mercure.resource_publisher',
        // Same, for cache invalidation
        'silverback.api_components.http_cache.purger',
        // Profiler panel data gathered across the request
        'silverback.api_components.data_collector.data',
        // Holds the JWT to be written as a cookie on the response
        'silverback.security.jwt_event_listener',
    ];

    public function test_bundle_services_holding_request_state_are_registered_with_the_services_resetter(): void
    {
        self::bootKernel();

        $resetter = self::getContainer()->get('services_resetter');
        self::assertInstanceOf(ServicesResetter::class, $resetter);

        $resetMethods = (new \ReflectionProperty(ServicesResetter::class, 'resetMethods'))->getValue($resetter);

        foreach (self::MUST_BE_RESETTABLE as $serviceId) {
            self::assertArrayHasKey(
                $serviceId,
                $resetMethods,
                \sprintf('"%s" holds per-request state but is not tagged kernel.reset, so it would carry that state into the next request in worker mode.', $serviceId)
            );
        }

        // Booting a kernel in debug registers Symfony's ErrorHandler, which PHPUnit reports as a
        // risky test. It cannot be handed back from here (restore_exception_handler does not clear
        // it), and risky is not a failure, so the notice is accepted.
        self::ensureKernelShutdown();
    }
}
