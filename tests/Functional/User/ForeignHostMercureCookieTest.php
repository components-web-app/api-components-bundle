<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\User;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;

final class ForeignHostMercureCookieTest extends FunctionalTestCase
{
    use UserTrait;

    private const string FOREIGN_HOST = 'https://example.org';

    public function test_a_login_on_a_host_outside_the_hubs_domain_signs_in_without_a_mercure_cookie(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        self::createUserWithRole('user', $password, 'ROLE_USER');

        self::requestJsonLd($client, 'POST', self::FOREIGN_HOST . '/login', ['username' => 'user', 'password' => $password]);

        self::assertResponseStatusCodeSame(204);
        self::assertResponseCookieAttributes('api_components', '/', 'lax');
        self::assertResponseHasNoCookie(self::mercureCookieName());
        self::assertForeignHostWarningLogged();
    }

    public function test_a_jwt_refresh_on_a_host_outside_the_hubs_domain_sets_a_new_jwt_cookie_without_a_mercure_cookie(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);
        self::setExpiredJwtCookie($client, $user);
        self::createRefreshToken($user);

        self::requestJsonLd($client, 'GET', self::FOREIGN_HOST . '/me');

        self::assertResponseStatusCodeSame(200);
        self::assertRefreshTokenExpired($user);
        self::assertResponseCookieAttributes('api_components', '/', 'lax');
        self::assertResponseHasNoCookie(self::mercureCookieName());
        self::assertForeignHostWarningLogged();
    }

    #[DataProvider('logoutMethods')]
    public function test_a_logout_on_a_host_outside_the_hubs_domain_clears_the_jwt_cookie_without_a_mercure_cookie(string $method): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, $method, self::FOREIGN_HOST . '/logout');

        self::assertResponseStatusCodeSame(200);
        self::assertJwtCookieCleared();
        self::assertResponseHasNoCookie(self::mercureCookieName());
        self::assertForeignHostWarningLogged();
    }

    public function test_a_failed_me_with_an_expired_session_on_a_host_outside_the_hubs_domain_clears_the_jwt_cookie_without_a_mercure_cookie(): void
    {
        $client = static::createClient();
        $user = self::loginAsAdmin($client);
        self::createRefreshToken($user, '-1 second');
        self::setExpiredJwtCookie($client, $user);

        self::requestJsonLd($client, 'GET', self::FOREIGN_HOST . '/me');

        self::assertResponseStatusCodeSame(401);
        self::assertJwtCookieCleared();
        self::assertResponseHasNoCookie(self::mercureCookieName());
        self::assertForeignHostWarningLogged();
    }

    public function test_a_failed_me_carrying_only_a_mercure_cookie_on_a_host_outside_the_hubs_domain_is_a_401_without_a_mercure_cookie(): void
    {
        $client = static::createClient();
        self::sendMercureCookieWithDraftTopics($client);

        self::requestJsonLd($client, 'GET', self::FOREIGN_HOST . '/me');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHasNoCookie(self::mercureCookieName());
        self::assertForeignHostWarningLogged();
    }

    public function test_an_invalid_jwt_on_a_host_outside_the_hubs_domain_clears_the_jwt_cookie_without_a_mercure_cookie(): void
    {
        $client = static::createClient();
        self::setInvalidBearerToken($client);

        self::requestJsonLd($client, 'GET', self::FOREIGN_HOST . '/');

        self::assertResponseStatusCodeSame(401);
        self::assertJwtCookieCleared();
        self::assertResponseHasNoCookie(self::mercureCookieName());
        self::assertForeignHostWarningLogged();
    }

    public static function logoutMethods(): iterable
    {
        yield 'GET' => ['GET'];
        yield 'POST' => ['POST'];
    }

    private static function assertForeignHostWarningLogged(): void
    {
        self::assertLogged(
            'a warning that the Mercure cookie cannot be set for example.org',
            static fn (LogRecord $record): bool => Level::Warning === $record->level && 'example.org' === ($record->context['host'] ?? null),
        );
    }
}
