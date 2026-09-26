<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Exception\DisallowedRequestOriginException;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnparseableRequestHeaderException;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Silverback\ApiComponentsBundle\Helper\RelativeUrlPath;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RefererUrlResolverTest extends TestCase
{
    private const ALLOWED = ['https://www\.example\.com'];

    private static function resolver(array $headers = [], array $allowedOrigins = self::ALLOWED, ?string $defaultOrigin = null, bool $withRequest = true): RefererUrlResolver
    {
        $requestStack = new RequestStack();
        if ($withRequest) {
            $request = new Request();
            foreach ($headers as $name => $value) {
                $request->headers->set($name, $value);
            }
            $requestStack->push($request);
        }

        return new RefererUrlResolver($requestStack, $allowedOrigins, $defaultOrigin);
    }

    private static function path(string $path = '/path-to-convert'): RelativeUrlPath
    {
        return RelativeUrlPath::fromConfiguration($path);
    }

    public function test_an_allowed_origin_pattern_is_compiled_anchored_and_case_insensitive(): void
    {
        self::assertSame('{\A(?:https://a\.com|https://b\.com)\z}i', RefererUrlResolver::allowedOriginRegex('https://a\.com|https://b\.com'));
    }

    public function test_a_bare_origin_is_normalised_and_anything_more_is_not_an_origin(): void
    {
        self::assertSame('https://www.example.com:8443', RefererUrlResolver::normaliseOrigin('HTTPS://WWW.example.com:8443/'));
        self::assertNull(RefererUrlResolver::normaliseOrigin('https://www.example.com/path'));
    }

    public function test_an_allowed_origin_header_is_used(): void
    {
        self::assertSame('https://www.example.com/path-to-convert', self::resolver(['origin' => 'https://www.example.com'])->getAbsoluteUrl(self::path()));
    }

    public function test_an_allowed_origin_header_with_a_trailing_slash_is_used(): void
    {
        self::assertSame('https://www.example.com/path-to-convert', self::resolver(['origin' => 'https://www.example.com/'])->getAbsoluteUrl(self::path()));
    }

    public function test_the_scheme_and_host_of_an_allowed_referer_header_are_used_without_its_path_or_query(): void
    {
        self::assertSame('https://www.example.com/path-to-convert', self::resolver(['referer' => 'https://www.example.com/some-path?a=b#c'])->getAbsoluteUrl(self::path()));
    }

    public function test_the_origin_is_resolved_on_its_own(): void
    {
        self::assertSame('https://www.example.com', self::resolver(['origin' => 'https://www.example.com'])->getOrigin());
    }

    public function test_the_origin_header_is_preferred_to_the_referer_header(): void
    {
        $resolver = self::resolver(['origin' => 'https://www.example.com', 'referer' => 'https://other.example.com'], ['https://www\.example\.com', 'https://other\.example\.com']);
        self::assertSame('https://www.example.com', $resolver->getOrigin());
    }

    public function test_a_disallowed_origin_header_is_refused_even_when_the_referer_is_allowed(): void
    {
        $this->expectException(DisallowedRequestOriginException::class);
        $this->expectExceptionMessage('The `origin` header is not an origin that links in emails may point to');
        self::resolver(['origin' => 'https://evil.example', 'referer' => 'https://www.example.com'])->getOrigin();
    }

    public function test_a_disallowed_referer_header_is_refused(): void
    {
        $this->expectException(DisallowedRequestOriginException::class);
        $this->expectExceptionMessage('The `referer` header is not an origin that links in emails may point to');
        self::resolver(['referer' => 'https://evil.example/path'])->getOrigin();
    }

    public function test_a_disallowed_origin_is_a_kind_of_unparseable_request_header(): void
    {
        $this->expectException(UnparseableRequestHeaderException::class);
        self::resolver(['origin' => 'https://evil.example'])->getOrigin();
    }

    public function test_a_disallowed_origin_falls_back_to_the_default_origin(): void
    {
        self::assertSame('https://default.example.com/path-to-convert', self::resolver(['origin' => 'https://evil.example'], defaultOrigin: 'https://default.example.com')->getAbsoluteUrl(self::path()));
    }

    public function test_an_allowed_origin_is_preferred_to_the_default_origin(): void
    {
        self::assertSame('https://www.example.com', self::resolver(['origin' => 'https://www.example.com'], defaultOrigin: 'https://default.example.com')->getOrigin());
    }

    public function test_the_default_origin_is_normalised(): void
    {
        self::assertSame('https://default.example.com:8443', self::resolver(defaultOrigin: 'HTTPS://Default.Example.com:8443/')->getOrigin());
    }

    public function test_nothing_is_allowed_by_default(): void
    {
        $this->expectException(DisallowedRequestOriginException::class);
        self::resolver(['origin' => 'https://www.example.com'], [])->getOrigin();
    }

    public function test_the_request_host_is_not_allowed_implicitly(): void
    {
        $requestStack = new RequestStack();
        $request = Request::create('https://www.example.com/password/reset/request/user');
        $request->headers->set('origin', 'https://www.example.com');
        $requestStack->push($request);

        $this->expectException(DisallowedRequestOriginException::class);
        (new RefererUrlResolver($requestStack))->getOrigin();
    }

    public function test_no_header_and_no_default_origin_is_refused(): void
    {
        $this->expectException(UnparseableRequestHeaderException::class);
        $this->expectExceptionMessage('To generate an absolute URL to the referrer, the request must have a `origin` or `referer` header present');
        self::resolver()->getOrigin();
    }

    public function test_no_header_falls_back_to_the_default_origin(): void
    {
        self::assertSame('https://default.example.com', self::resolver(defaultOrigin: 'https://default.example.com')->getOrigin());
    }

    public function test_no_request_and_no_default_origin_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('To generate an absolute URL to the referrer, there must be a valid master request');
        self::resolver(withRequest: false)->getOrigin();
    }

    public function test_no_request_falls_back_to_the_default_origin(): void
    {
        self::assertSame('https://default.example.com', self::resolver(defaultOrigin: 'https://default.example.com', withRequest: false)->getOrigin());
    }

    public function test_a_default_origin_url_ignores_an_allowed_request_origin(): void
    {
        $resolver = self::resolver(['origin' => 'https://www.example.com'], self::ALLOWED, 'https://admin.example.com:8443/');

        self::assertSame('https://admin.example.com:8443/_cwa/orphaned', $resolver->getDefaultOriginUrl(RelativeUrlPath::fromConfiguration('_cwa/orphaned')));
    }

    public function test_a_default_origin_url_needs_a_default_origin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::resolver(['origin' => 'https://www.example.com'], self::ALLOWED, '')->getDefaultOriginUrl(RelativeUrlPath::fromConfiguration('/_cwa/orphaned'));
    }

    public function test_a_default_origin_url_needs_a_valid_default_origin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::resolver([], self::ALLOWED, 'not an origin', false)->getDefaultOriginUrl(RelativeUrlPath::fromConfiguration('/_cwa/orphaned'));
    }

    public function test_an_empty_default_origin_is_no_default(): void
    {
        $this->expectException(UnparseableRequestHeaderException::class);
        self::resolver(defaultOrigin: '')->getOrigin();
    }

    public static function invalidDefaultOrigins(): iterable
    {
        yield 'no scheme' => ['default.example.com'];
        yield 'not http' => ['ftp://default.example.com'];
        yield 'no host' => ['https://'];
        yield 'with a path' => ['https://default.example.com/path'];
        yield 'with a query' => ['https://default.example.com/?a=b'];
        yield 'with a fragment' => ['https://default.example.com/#a'];
        yield 'with userinfo' => ['https://user@default.example.com'];
        yield 'with a backslash in the host' => ['https://evil.example\\default.example.com'];
    }

    #[DataProvider('invalidDefaultOrigins')]
    public function test_a_default_origin_that_is_not_an_origin_is_a_configuration_error(string $defaultOrigin): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The configured default origin for links in emails `%s` must be a scheme and host with an optional port', $defaultOrigin));
        self::resolver(['origin' => 'https://www.example.com'], defaultOrigin: $defaultOrigin)->getOrigin();
    }

    public function test_an_invalid_allowed_origin_pattern_is_a_configuration_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The allowed origin pattern `https://(www` for links in emails is not a valid regular expression');
        self::resolver(['origin' => 'https://www.example.com'], ['https://(www'])->getOrigin();
    }

    public static function allowedOrigins(): iterable
    {
        yield 'default https port dropped' => ['https://www.example.com:443', ['https://www\.example\.com'], 'https://www.example.com'];
        yield 'default http port dropped' => ['http://www.example.com:80/some-path', ['http://www\.example\.com'], 'http://www.example.com'];
        yield 'alternative port kept' => ['https://www.example.com:999/some-path', ['https://www\.example\.com:999'], 'https://www.example.com:999'];
        yield 'https port on http kept' => ['http://www.example.com:443', ['http://www\.example\.com:443'], 'http://www.example.com:443'];
        yield 'http port on https kept' => ['https://www.example.com:80', ['https://www\.example\.com:80'], 'https://www.example.com:80'];
        yield 'case normalised' => ['HTTPS://WWW.Example.COM', ['https://www\.example\.com'], 'https://www.example.com'];
        yield 'pattern matched case-insensitively' => ['https://www.example.com', ['HTTPS://WWW\.EXAMPLE\.COM'], 'https://www.example.com'];
        yield 'userinfo dropped' => ['https://user:pass@www.example.com/path', ['https://www\.example\.com'], 'https://www.example.com'];
        yield 'second pattern matches' => ['https://b.example.com', ['https://a\.example\.com', 'https://b\.example\.com'], 'https://b.example.com'];
        yield 'alternation inside one pattern' => ['https://b.example.com', ['https://a\.example\.com|https://b\.example\.com'], 'https://b.example.com'];
        yield 'explicit anchors still work' => ['https://www.example.com', ['^https://www\.example\.com$'], 'https://www.example.com'];
        yield 'optional port pattern without port' => ['https://www.example.com', ['https://www\.example\.com(:8443)?'], 'https://www.example.com'];
        yield 'optional port pattern with port' => ['https://www.example.com:8443', ['https://www\.example\.com(:8443)?'], 'https://www.example.com:8443'];
        yield 'ipv6 host' => ['http://[::1]:8080', ['http://\[::1\]:8080'], 'http://[::1]:8080'];
        yield 'hyphenated subdomain wildcard' => ['https://my-app.example.com', ['https://[a-z0-9-]+\.example\.com'], 'https://my-app.example.com'];
    }

    #[DataProvider('allowedOrigins')]
    public function test_an_origin_matching_an_allowed_pattern_is_normalised_and_used(string $header, array $allowed, string $expected): void
    {
        self::assertSame($expected, self::resolver(['origin' => $header], $allowed)->getOrigin());
        self::assertSame($expected, self::resolver(['referer' => $header], $allowed)->getOrigin());
    }

    public static function disallowedOrigins(): iterable
    {
        yield 'another host' => ['https://evil.example', ['https://www\.example\.com']];
        yield 'allowed host as a prefix' => ['https://www.example.com.evil.example', ['https://www\.example\.com']];
        yield 'allowed host as a suffix' => ['https://evil-www.example.com', ['https://www\.example\.com']];
        yield 'alternation cannot escape the anchors' => ['https://b.example.com.evil.example', ['https://a\.example\.com|https://b\.example\.com']];
        yield 'another port' => ['https://www.example.com:8443', ['https://www\.example\.com']];
        yield 'another scheme' => ['http://www.example.com', ['https://www\.example\.com']];
        yield 'not http even when the pattern allows anything' => ['ftp://www.example.com', ['.*']];
        yield 'javascript scheme' => ['javascript://www.example.com', ['.*']];
        yield 'backslash in the host' => ['https://evil.example\\.example.com', ['https://.*\.example\.com']];
        yield 'percent in the host' => ['https://evil.example%2f.example.com', ['https://.*\.example\.com']];
        yield 'origin null' => ['null', ['.*']];
    }

    #[DataProvider('disallowedOrigins')]
    public function test_an_origin_not_matching_an_allowed_pattern_is_refused(string $header, array $allowed): void
    {
        $this->expectException(UnparseableRequestHeaderException::class);
        self::resolver(['origin' => $header], $allowed)->getOrigin();
    }

    public static function unparseableHeaders(): iterable
    {
        yield ['origin', 'invalid-scheme.com:90/path', 'Could not extract `scheme` while parsing the `origin` header'];
        yield ['origin', 'http:///path', 'Could not extract `host` while parsing the `origin` header'];
        yield ['origin', '', 'Could not extract `host` while parsing the `origin` header'];
        yield ['referer', 'invalid-scheme.com:90/path', 'Could not extract `scheme` while parsing the `referer` header'];
        yield ['referer', 'http:///path', 'Could not extract `host` while parsing the `referer` header'];
        yield ['referer', '', 'Could not extract `host` while parsing the `referer` header'];
        yield ['referer', 'https://:90/abc', 'Could not extract `host` while parsing the `referer` header'];
    }

    #[DataProvider('unparseableHeaders')]
    public function test_an_unparseable_header_is_refused(string $header, string $value, string $message): void
    {
        $this->expectException(UnparseableRequestHeaderException::class);
        $this->expectExceptionMessage($message);
        self::resolver([$header => $value], ['.*'])->getOrigin();
    }

    public static function unparseableHeaderValues(): iterable
    {
        foreach (self::unparseableHeaders() as $key => [$header, $value]) {
            yield $key => [$header, $value];
        }
    }

    #[DataProvider('unparseableHeaderValues')]
    public function test_an_unparseable_header_falls_back_to_the_default_origin(string $header, string $value): void
    {
        self::assertSame('https://default.example.com', self::resolver([$header => $value], ['.*'], 'https://default.example.com')->getOrigin());
    }
}
