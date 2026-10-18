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

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Cookie\JWTCookieProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\EventListener\Jwt\JWTClearTokenListener;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class JWTClearTokenListenerTest extends TestCase
{
    private const string MERCURE_COOKIE = '__Secure-mercure_access_token';

    /**
     * @return iterable<string, array{array<string, string>, list<string>}>
     */
    public static function failedMeRequestCookies(): iterable
    {
        yield 'no cookies' => [[], []];
        yield 'only the JWT cookie' => [['api_components' => 'jwt'], ['api_components', self::MERCURE_COOKIE]];
        yield 'only the mercure cookie' => [[self::MERCURE_COOKIE => 'token'], [self::MERCURE_COOKIE]];
        yield 'both cookies' => [['api_components' => 'jwt', self::MERCURE_COOKIE => 'token'], ['api_components', self::MERCURE_COOKIE]];
        yield 'an unrelated cookie' => [['other' => 'value'], []];
    }

    /**
     * @param array<string, string> $requestCookies
     * @param list<string>          $expectedCookies
     */
    #[DataProvider('failedMeRequestCookies')]
    public function test_a_failed_me_request_clears_the_cookies_it_carries(array $requestCookies, array $expectedCookies): void
    {
        $response = new Response('', Response::HTTP_UNAUTHORIZED);
        $this->buildListener()->onKernelResponse($this->makeResponseEvent($this->makeMeRequest($requestCookies), $response));

        self::assertSame($expectedCookies, $this->cookieNames($response));
        foreach ($response->headers->getCookies() as $cookie) {
            self::assertTrue($cookie->isCleared());
        }
    }

    public function test_a_successful_me_request_clears_nothing(): void
    {
        $response = new Response();
        $this->buildListener()->onKernelResponse($this->makeResponseEvent($this->makeMeRequest(['api_components' => 'jwt', self::MERCURE_COOKIE => 'token']), $response));

        self::assertSame([], $this->cookieNames($response));
    }

    public function test_a_failed_request_to_another_operation_clears_nothing(): void
    {
        $request = $this->makeMeRequest(['api_components' => 'jwt', self::MERCURE_COOKIE => 'token']);
        $request->attributes->set('_api_operation_name', '_api_other');
        $response = new Response('', Response::HTTP_UNAUTHORIZED);
        $this->buildListener()->onKernelResponse($this->makeResponseEvent($request, $response));

        self::assertSame([], $this->cookieNames($response));
    }

    public function test_a_failed_me_sub_request_clears_nothing(): void
    {
        $response = new Response('', Response::HTTP_UNAUTHORIZED);
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $this->makeMeRequest([self::MERCURE_COOKIE => 'token']), HttpKernelInterface::SUB_REQUEST, $response);
        $this->buildListener()->onKernelResponse($event);

        self::assertSame([], $this->cookieNames($response));
    }

    public function test_an_expired_jwt_clears_the_jwt_and_mercure_cookies(): void
    {
        $response = new Response();
        $this->buildListener()->onJwtExpired(new JWTExpiredEvent(new AuthenticationException(), $response));

        self::assertSame(['api_components', self::MERCURE_COOKIE], $this->cookieNames($response));
    }

    public function test_an_invalid_jwt_clears_the_jwt_and_mercure_cookies(): void
    {
        $response = new Response();
        $this->buildListener()->onJwtInvalid(new JWTInvalidEvent(new AuthenticationException(), $response));

        self::assertSame(['api_components', self::MERCURE_COOKIE], $this->cookieNames($response));
    }

    private function buildListener(): JWTClearTokenListener
    {
        $mercure = $this->createStub(MercureAuthorization::class);
        $mercure->method('getClearAuthorizationCookie')->willReturn(Cookie::create(self::MERCURE_COOKIE, null, 1, '/.well-known/mercure'));

        return new JWTClearTokenListener(new JWTCookieProvider('api_components'), $mercure);
    }

    /**
     * @param array<string, string> $cookies
     */
    private function makeMeRequest(array $cookies): Request
    {
        $request = new Request(cookies: $cookies);
        $request->attributes->set('_api_operation_name', '_api_me');

        return $request;
    }

    private function makeResponseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }

    /**
     * @return list<string>
     */
    private function cookieNames(Response $response): array
    {
        return array_map(static fn (Cookie $cookie): string => $cookie->getName(), $response->headers->getCookies());
    }
}
