<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Utility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Utility\MercureCookieDomain;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\Exception\RuntimeException;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;

final class MercureCookieDomainTest extends TestCase
{
    #[DataProvider('hosts')]
    public function test_a_host_is_outside_the_hubs_domain_exactly_when_symfony_mercure_refuses_its_cookie(string $requestHost, string $hubPublicUrl, bool $outside): void
    {
        self::assertSame($outside, MercureCookieDomain::isOutside($requestHost, $hubPublicUrl));
        self::assertSame($outside, self::symfonyMercureRefuses($requestHost, $hubPublicUrl));
    }

    public static function hosts(): iterable
    {
        yield 'the hub host itself' => ['example.com', 'https://example.com/.well-known/mercure', false];
        yield 'the hub host in another case' => ['Example.COM', 'https://example.com/.well-known/mercure', false];
        yield 'a parent of the hub host' => ['example.com', 'https://hub.example.com/.well-known/mercure', false];
        yield 'a sibling subdomain' => ['www.example.com', 'https://hub.example.com/.well-known/mercure', false];
        yield 'a subdomain of the hub host' => ['www.example.com', 'https://example.com/.well-known/mercure', false];
        yield 'a deeper subdomain' => ['a.b.example.com', 'https://hub.example.com/.well-known/mercure', false];
        yield 'a hub url with no host' => ['example.org', '/.well-known/mercure', false];
        yield 'another registrable domain' => ['example.org', 'https://example.com/.well-known/mercure', true];
        yield 'another domain under the same suffix' => ['other.com', 'https://hub.example.com/.well-known/mercure', true];
        yield 'a tunnel host' => ['abc123.trycloudflare.com', 'https://example.com/.well-known/mercure', true];
        yield 'a single label host' => ['localhost', 'https://example.com/.well-known/mercure', true];
        yield 'a host only ending like the hub' => ['ample.com', 'https://example.com/.well-known/mercure', true];
    }

    private static function symfonyMercureRefuses(string $requestHost, string $hubPublicUrl): bool
    {
        $authorization = new Authorization(new HubRegistry(new MockHub($hubPublicUrl, new StaticTokenProvider('jwt'), static fn (): string => 'id')));

        try {
            $authorization->createClearCookie(Request::create('https://' . $requestHost . '/'));
        } catch (RuntimeException) {
            return true;
        }

        return false;
    }
}
