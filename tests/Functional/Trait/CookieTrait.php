<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use ApiPlatform\Test\Client;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;

trait CookieTrait
{
    protected static function mercureCookieName(): string
    {
        return '__Secure-mercure_access_token';
    }

    protected static function responseCookie(string $name): Cookie
    {
        foreach (self::getClient()->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        self::fail(\sprintf('The response sets no "%s" cookie.', $name));
    }

    protected static function assertResponseCookieAttributes(string $name, string $path, string $sameSite): void
    {
        $cookie = static::responseCookie($name);

        self::assertTrue($cookie->isSecure(), \sprintf('The cookie "%s" is not secure.', $name));
        self::assertTrue($cookie->isHttpOnly(), \sprintf('The cookie "%s" is not httponly.', $name));
        self::assertSame($sameSite, $cookie->getSameSite(), \sprintf('The cookie "%s" has the wrong samesite attribute.', $name));
        self::assertSame($path, $cookie->getPath(), \sprintf('The cookie "%s" has the wrong path.', $name));
    }

    protected static function assertResponseHasNoCookie(string $name): void
    {
        $names = array_map(static fn (Cookie $cookie): string => $cookie->getName(), self::getClient()->getResponse()->headers->getCookies());

        self::assertNotContains($name, $names, \sprintf('The response sets the cookie "%s".', $name));
    }

    protected static function assertResponseCookieExpiresWithin(string $name, int $seconds): void
    {
        $remaining = static::responseCookie($name)->getExpiresTime() - time();

        self::assertLessThan($seconds, $remaining, \sprintf('The cookie "%s" expires in %d seconds.', $name, $remaining));
    }

    /**
     * @return list<string>
     */
    protected static function mercureCookieTopics(): array
    {
        $topics = [];
        foreach (static::mercureCookiePayload()['authorization_details'] ?? [] as $detail) {
            if (!\in_array('subscribe', $detail['actions'], true)) {
                continue;
            }
            foreach ($detail['topics'] as $topic) {
                self::assertSame('urlpattern', $topic['match_type'] ?? null, \sprintf('The mercure cookie grants "%s" as an exact topic instead of a URL pattern.', $topic['match']));
                $topics[] = $topic['match'];
            }
        }

        return $topics;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function mercureCookiePayload(): array
    {
        $parts = explode('.', (string) static::responseCookie(static::mercureCookieName())->getValue());
        self::assertCount(3, $parts, 'The mercure cookie does not hold a JWT.');

        return json_decode(base64_decode(strtr($parts[1], '-_', '+/'), true), true, 512, \JSON_THROW_ON_ERROR);
    }

    protected static function assertMercureCookieGrantsTopicMatching(string $pattern): void
    {
        $topics = static::mercureCookieTopics();

        self::assertNotEmpty(preg_grep($pattern, $topics), \sprintf('The mercure cookie grants no topic matching "%s". Topics: %s', $pattern, implode(', ', $topics)));
    }

    protected static function assertMercureCookieGrantsNoTopicMatching(string $pattern): void
    {
        $topics = static::mercureCookieTopics();

        self::assertEmpty(preg_grep($pattern, $topics), \sprintf('The mercure cookie grants topics matching "%s". Topics: %s', $pattern, implode(', ', $topics)));
    }

    protected static function assertMercureCookieCleared(): void
    {
        $cookie = static::responseCookie(static::mercureCookieName());

        self::assertTrue($cookie->isCleared(), \sprintf('The mercure cookie is not expired: it expires at %s.', date(\DATE_ATOM, $cookie->getExpiresTime())));
        self::assertContains($cookie->getValue(), [null, '', 'deleted'], 'The mercure cookie still carries a token.');
    }

    protected static function assertMercureCookieAndTokenExpireIn(int $seconds): void
    {
        $tokenExpiry = (int) static::mercureCookiePayload()['exp'];

        self::assertEqualsWithDelta(time() + $seconds, $tokenExpiry, 5, 'The mercure token does not expire when expected.');
        self::assertEqualsWithDelta($tokenExpiry, static::responseCookie(static::mercureCookieName())->getExpiresTime(), 1, 'The mercure cookie and its token expire at different times.');
    }

    protected static function sendMercureCookieWithDraftTopics(Client $client): void
    {
        $cookie = static::getContainer()->get(Authorization::class)->createCookie(Request::create('https://example.com/'), ['urlpattern' => ['https://example.com/component/dummy_publishable_components/:id\\?draft=1']]);
        $client->getKernelBrowser()->getCookieJar()->set(new BrowserCookie($cookie->getName(), (string) $cookie->getValue()));
        static::rebootKernel();
    }
}
