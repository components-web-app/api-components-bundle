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

use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Cookie\JWTCookieProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\RefreshToken\Storage\RefreshTokenStorageInterface;
use Silverback\ApiComponentsBundle\Security\EventListener\LogoutListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutListenerTest extends TestCase
{
    private const string MERCURE_COOKIE = '__Secure-mercure_access_token';

    public function test_a_logout_clears_the_jwt_and_mercure_cookies(): void
    {
        $event = $this->logout(true);

        self::assertSame(['api_components', self::MERCURE_COOKIE], $this->cookieNames($event));
        self::assertSame(200, $event->getResponse()?->getStatusCode());
    }

    public function test_a_logout_on_a_host_with_no_mercure_cookie_clears_only_the_jwt_cookie(): void
    {
        $event = $this->logout(false);

        self::assertSame(['api_components'], $this->cookieNames($event));
        self::assertSame(200, $event->getResponse()?->getStatusCode());
    }

    private function logout(bool $mercureCookie): LogoutEvent
    {
        $mercure = $this->createStub(MercureAuthorization::class);
        $mercure->method('getClearAuthorizationCookie')->willReturn($mercureCookie ? Cookie::create(self::MERCURE_COOKIE, null, 1, '/.well-known/mercure') : null);
        $event = new LogoutEvent(new Request(), new NullToken());

        (new LogoutListener($this->createStub(RefreshTokenStorageInterface::class), new JWTCookieProvider('api_components'), $mercure))($event);

        return $event;
    }

    /**
     * @return list<string>
     */
    private function cookieNames(LogoutEvent $event): array
    {
        return array_map(static fn (Cookie $cookie): string => $cookie->getName(), $event->getResponse()?->headers->getCookies() ?? []);
    }
}
