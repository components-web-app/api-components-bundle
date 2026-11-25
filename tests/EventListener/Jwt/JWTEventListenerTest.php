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

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
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
use Symfony\Component\Security\Core\User\UserInterface;

class JWTEventListenerTest extends TestCase
{
    private function buildListener(?CwaCollectorData $collectorData = null, bool $mercureCookie = true): JWTEventListener
    {
        $mercure = $this->createStub(MercureAuthorization::class);
        $mercure->method('getAuthorizationCookie')->willReturn($mercureCookie ? new Cookie('__Secure-mercure_access_token', 'value') : null);

        return new JWTEventListener(
            $this->createStub(RoleHierarchy::class),
            new JWTCookieProvider('api_components'),
            $mercure,
            $collectorData,
        );
    }

    private function makeResponseEvent(int $requestType = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            $requestType,
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
        $collectorData = new CwaCollectorData();
        $listener = $this->buildListener($collectorData);

        $listener->onJWTRefreshed(new JWTRefreshedEvent('header.payload.signature'));

        $collector = new \Silverback\ApiComponentsBundle\DataCollector\CwaDataCollector($collectorData);
        $collector->collect(new Request(), new Response());

        self::assertTrue($collector->isJwtRefreshIssued(), 'recordJwtRefreshIssued() must be called during onJWTRefreshed');
    }

    public function test_on_kernel_response_records_jwt_cookie_present_when_cookie_in_request(): void
    {
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
        self::assertSame('api_components', $collector->getJwtCookieName());
    }

    public function test_on_kernel_response_does_not_record_jwt_cookie_present_when_cookie_absent(): void
    {
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
        $listener = $this->buildListener();
        $listener->onJWTRefreshed(new JWTRefreshedEvent('header.payload.signature'));

        $responseEvent = $this->makeResponseEvent();
        $listener->onKernelResponse($responseEvent);

        $cookieNames = array_map(
            static fn (Cookie $c) => $c->getName(),
            $responseEvent->getResponse()->headers->getCookies(),
        );

        self::assertContains('__Secure-mercure_access_token', $cookieNames, 'Mercure authorization cookie must be set on the response when token is present');
    }

    public function test_token_consumed_on_first_response_not_reused(): void
    {
        $listener = $this->buildListener();

        $listener->onJWTRefreshed(new JWTRefreshedEvent('header.payload.signature'));

        $first = $this->makeResponseEvent();
        $listener->onKernelResponse($first);
        $firstCookieNames = array_map(static fn (Cookie $c) => $c->getName(), $first->getResponse()->headers->getCookies());
        self::assertContains('api_components', $firstCookieNames);

        $second = $this->makeResponseEvent();
        $listener->onKernelResponse($second);
        $secondCookieNames = array_map(static fn (Cookie $c) => $c->getName(), $second->getResponse()->headers->getCookies());
        self::assertNotContains('api_components', $secondCookieNames, 'Token must not be reused on a second response');
    }

    public function test_a_sub_request_response_leaves_the_refreshed_token_for_the_main_response(): void
    {
        $listener = $this->buildListener();
        $listener->onJWTRefreshed(new JWTRefreshedEvent('header.payload.signature'));

        $subRequest = $this->makeResponseEvent(HttpKernelInterface::SUB_REQUEST);
        $listener->onKernelResponse($subRequest);
        $main = $this->makeResponseEvent();
        $listener->onKernelResponse($main);

        self::assertSame([], $subRequest->getResponse()->headers->getCookies());
        self::assertSame(['api_components', '__Secure-mercure_access_token'], array_map(static fn (Cookie $c) => $c->getName(), $main->getResponse()->headers->getCookies()));
    }

    public function test_a_refresh_on_a_host_with_no_mercure_cookie_sets_only_the_jwt_cookie(): void
    {
        $listener = $this->buildListener(mercureCookie: false);
        $listener->onJWTRefreshed(new JWTRefreshedEvent('header.payload.signature'));

        $responseEvent = $this->makeResponseEvent();
        $listener->onKernelResponse($responseEvent);

        self::assertSame(['api_components'], array_map(static fn (Cookie $c) => $c->getName(), $responseEvent->getResponse()->headers->getCookies()));
    }

    public function test_a_login_sets_the_mercure_cookie(): void
    {
        $response = new Response();
        $this->buildListener()->onJWTAuthenticationSuccess(new AuthenticationSuccessEvent([], $this->createStub(UserInterface::class), $response));

        self::assertSame(['__Secure-mercure_access_token'], array_map(static fn (Cookie $c) => $c->getName(), $response->headers->getCookies()));
    }

    public function test_a_login_on_a_host_with_no_mercure_cookie_sets_no_mercure_cookie(): void
    {
        $response = new Response();
        $this->buildListener(mercureCookie: false)->onJWTAuthenticationSuccess(new AuthenticationSuccessEvent([], $this->createStub(UserInterface::class), $response));

        self::assertSame([], $response->headers->getCookies());
    }
}
