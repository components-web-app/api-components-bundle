<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Security\EventListener;

use ApiPlatform\Metadata\Get;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\Security\EventListener\AccessDeniedListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class AccessDeniedListenerTest extends TestCase
{
    private const string MERCURE_COOKIE = '__Secure-mercure_access_token';

    public function test_a_failed_me_request_carrying_the_mercure_cookie_clears_it(): void
    {
        $response = new JWTAuthenticationFailureResponse();
        $this->onPostRespond($this->makeRequest('_api_me', [self::MERCURE_COOKIE => 'token']), $response);

        self::assertSame([self::MERCURE_COOKIE], $this->cookieNames($response));
        self::assertTrue($response->headers->getCookies()[0]->isCleared());
    }

    public function test_a_failed_me_request_without_the_mercure_cookie_sets_no_cookie(): void
    {
        $response = new JWTAuthenticationFailureResponse();
        $this->onPostRespond($this->makeRequest('_api_me', ['other' => 'value']), $response);

        self::assertSame([], $this->cookieNames($response));
    }

    public function test_a_failure_on_another_operation_sets_no_cookie(): void
    {
        $response = new JWTAuthenticationFailureResponse();
        $this->onPostRespond($this->makeRequest('_api_other', [self::MERCURE_COOKIE => 'token']), $response);

        self::assertSame([], $this->cookieNames($response));
    }

    public function test_a_request_with_no_operation_sets_no_cookie(): void
    {
        $response = new JWTAuthenticationFailureResponse();
        $this->onPostRespond(new Request(cookies: [self::MERCURE_COOKIE => 'token']), $response);

        self::assertSame([], $this->cookieNames($response));
    }

    public function test_another_kind_of_response_sets_no_cookie(): void
    {
        $response = new Response('', Response::HTTP_UNAUTHORIZED);
        $this->onPostRespond($this->makeRequest('_api_me', [self::MERCURE_COOKIE => 'token']), $response);

        self::assertSame([], $this->cookieNames($response));
    }

    private function onPostRespond(Request $request, Response $response): void
    {
        $mercure = $this->createStub(MercureAuthorization::class);
        $mercure->method('getClearAuthorizationCookie')->willReturn(Cookie::create(self::MERCURE_COOKIE, null, 1, '/.well-known/mercure'));

        (new AccessDeniedListener($mercure))->onPostRespond(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));
    }

    /**
     * @param array<string, string> $cookies
     */
    private function makeRequest(string $operationName, array $cookies): Request
    {
        $request = new Request(cookies: $cookies);
        $request->attributes->set('_api_operation', new Get(name: $operationName));

        return $request;
    }

    /**
     * @return list<string>
     */
    private function cookieNames(Response $response): array
    {
        return array_map(static fn (Cookie $cookie): string => $cookie->getName(), $response->headers->getCookies());
    }
}
