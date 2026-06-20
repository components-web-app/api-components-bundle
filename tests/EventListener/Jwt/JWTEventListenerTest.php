<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\EventListener\Jwt;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Cookie\JWTCookieProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataCollector\CwaCollectorData;
use Silverback\ApiComponentsBundle\Event\JWTRefreshedEvent;
use Silverback\ApiComponentsBundle\EventListener\Jwt\JWTEventListener;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class JWTEventListenerTest extends TestCase
{
    private function buildListener(?CwaCollectorData $collectorData = null): JWTEventListener
    {
        $mercure = $this->createStub(MercureAuthorization::class);
        $mercure->method('getAuthorizationCookie')->willReturn(new Cookie('mercureAuthorization', 'value'));

        return new JWTEventListener(
            $this->createStub(RoleHierarchy::class),
            new JWTCookieProvider('api_components'),
            $mercure,
            $collectorData,
        );
    }

    private function makeResponseEvent(): ResponseEvent
    {
        return new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );
    }

    public function test_no_cookie_set_when_no_jwt_refresh_occurred(): void
    {
        $listener = $this->buildListener();
        $responseEvent = $this->makeResponseEvent();
        $listener->onKernelResponse($responseEvent);

        $cookies = $responseEvent->getResponse()->headers->getCookies();
        $cookieNames = array_map(static fn (Cookie $c) => $c->getName(), $cookies);
        self::assertNotContains('api_components', $cookieNames);
    }

    public function test_reset_prevents_token_leaking_to_next_request(): void
    {
        $listener = $this->buildListener();

        $listener->onJWTRefreshed(new JWTRefreshedEvent('header.payload.signature'));

        // Simulate worker-mode reset between requests
        $listener->reset();

        $responseEvent = $this->makeResponseEvent();
        $listener->onKernelResponse($responseEvent);

        $cookieNames = array_map(
            static fn (Cookie $c) => $c->getName(),
            $responseEvent->getResponse()->headers->getCookies(),
        );
        self::assertNotContains('api_components', $cookieNames, 'Token must not leak to subsequent request after reset()');
    }

    public function test_on_jwt_refreshed_records_refresh_in_collector_data(): void
    {
        // Mutant 33 removes the recordJwtRefreshIssued() call — this test kills it
        $collectorData = new CwaCollectorData();
        $listener = $this->buildListener($collectorData);

        $listener->onJWTRefreshed(new JWTRefreshedEvent('header.payload.signature'));

        // Collect into a data collector to expose the recorded state
        $collector = new \Silverback\ApiComponentsBundle\DataCollector\CwaDataCollector($collectorData);
        $collector->collect(new Request(), new Response());

        self::assertTrue($collector->isJwtRefreshIssued(), 'recordJwtRefreshIssued() must be called during onJWTRefreshed');
    }

    public function test_on_kernel_response_records_jwt_cookie_present_when_cookie_in_request(): void
    {
        // Mutant 34 negates the if condition — this test kills it
        $collectorData = new CwaCollectorData();
        $listener = $this->buildListener($collectorData);

        $request = new Request();
        $request->cookies->set('api_components', 'some_token_value');

        $responseEvent = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );
        $listener->onKernelResponse($responseEvent);

        $collector = new \Silverback\ApiComponentsBundle\DataCollector\CwaDataCollector($collectorData);
        $collector->collect(new Request(), new Response());

        self::assertTrue($collector->isJwtCookiePresent(), 'JWT cookie present must be recorded when request contains the cookie');
    }

    public function test_on_kernel_response_does_not_record_jwt_cookie_present_when_cookie_absent(): void
    {
        // Complements test above — no cookie in request must NOT trigger recordJwtCookiePresent
        $collectorData = new CwaCollectorData();
        $listener = $this->buildListener($collectorData);

        $responseEvent = $this->makeResponseEvent();
        $listener->onKernelResponse($responseEvent);

        $collector = new \Silverback\ApiComponentsBundle\DataCollector\CwaDataCollector($collectorData);
        $collector->collect(new Request(), new Response());

        self::assertFalse($collector->isJwtCookiePresent(), 'JWT cookie must NOT be recorded when request has no cookie');
    }

    public function test_on_kernel_response_sets_mercure_authorization_cookie_when_token_present(): void
    {
        // Mutant 35 removes the mercureAuthorization->getAuthorizationCookie() call — this test kills it
        $listener = $this->buildListener();
        $listener->onJWTRefreshed(new JWTRefreshedEvent('header.payload.signature'));

        $responseEvent = $this->makeResponseEvent();
        $listener->onKernelResponse($responseEvent);

        $cookieNames = array_map(
            static fn (Cookie $c) => $c->getName(),
            $responseEvent->getResponse()->headers->getCookies(),
        );

        self::assertContains('mercureAuthorization', $cookieNames, 'Mercure authorization cookie must be set on the response when token is present');
    }

    public function test_token_consumed_on_first_response_not_reused(): void
    {
        $listener = $this->buildListener();

        $listener->onJWTRefreshed(new JWTRefreshedEvent('header.payload.signature'));

        // First response: JWT cookie is set
        $first = $this->makeResponseEvent();
        $listener->onKernelResponse($first);
        $firstCookieNames = array_map(static fn (Cookie $c) => $c->getName(), $first->getResponse()->headers->getCookies());
        self::assertContains('api_components', $firstCookieNames);

        // Second response without reset or new refresh: token already consumed
        $second = $this->makeResponseEvent();
        $listener->onKernelResponse($second);
        $secondCookieNames = array_map(static fn (Cookie $c) => $c->getName(), $second->getResponse()->headers->getCookies());
        self::assertNotContains('api_components', $secondCookieNames, 'Token must not be reused on a second response');
    }
}
