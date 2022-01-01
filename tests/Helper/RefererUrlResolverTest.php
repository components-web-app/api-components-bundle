<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\Helper;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnparseableRequestHeaderException;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RefererUrlResolverTest extends TestCase
{
    /**
     * @var MockObject|RequestStack
     */
    private MockObject $requestStackMock;
    private RefererUrlResolver $refererUrlHelper;

    protected function setUp(): void
    {
        $this->requestStackMock = $this->createMock(RequestStack::class);
        $this->refererUrlHelper = new RefererUrlResolver($this->requestStackMock);
    }

    public function test_do_not_change_absolute_paths(): void
    {
        $this->assertEquals('https://website.com', $this->refererUrlHelper->getAbsoluteUrl('https://website.com'));
        $this->assertEquals('//website.com', $this->refererUrlHelper->getAbsoluteUrl('//website.com'));
    }

    public function test_error_thrown_when_no_request(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('To generate an absolute URL to the referrer, there must be a valid master request');
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn(null);
        $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert');
    }

    public function test_error_thrown_when_no_accepted_headers(): void
    {
        $this->expectException(UnparseableRequestHeaderException::class);
        $this->expectExceptionMessage('To generate an absolute URL to the referrer, the request must have a `origin` or `referer` header present');
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn(new Request());
        $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert');
    }

    public function test_get_absolute_url_using_origin(): void
    {
        $request = new Request();
        $request->headers->set('origin', 'https://www.example.com');
        $this->requestStackMock
            ->expects($this->exactly(2))
            ->method('getMainRequest')
            ->willReturn($request);

        $this->assertEquals('https://www.example.com/path-to-convert', $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert'));
        $this->assertEquals('https://www.example.com/path-to-convert', $this->refererUrlHelper->getAbsoluteUrl('path-to-convert'));
    }

    public function test_get_absolute_url_using_origin_with_trailing_slash(): void
    {
        $request = new Request();
        $request->headers->set('origin', 'https://www.example.com/');
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->assertEquals('https://www.example.com/path-to-convert', $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert'));
    }

    public function test_error_thrown_when_invalid_scheme_in_origin_header(): void
    {
        $request = new Request();
        $request->headers->set('origin', 'invalid-scheme.com:90/path');

        $this->expectException(UnparseableRequestHeaderException::class);
        $this->expectExceptionMessage('Could not extract `scheme` while parsing the `origin` header');
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);
        $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert');
    }

    public function test_error_thrown_when_invalid_host_in_origin_header(): void
    {
        $request = new Request();
        $request->headers->set('origin', 'http:///path');

        $this->expectException(UnparseableRequestHeaderException::class);
        $this->expectExceptionMessage('Could not extract `host` while parsing the `origin` header');
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);
        $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert');
    }

    public function test_error_thrown_when_invalid_scheme_in_referer_header(): void
    {
        $request = new Request();
        $request->headers->set('referer', 'invalid-scheme.com:90/path');

        $this->expectException(UnparseableRequestHeaderException::class);
        $this->expectExceptionMessage('Could not extract `scheme` while parsing the `referer` header');
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);
        $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert');
    }

    public function test_error_thrown_when_invalid_host_in_referer_header(): void
    {
        $request = new Request();
        $request->headers->set('referer', 'http:///path');

        $this->expectException(UnparseableRequestHeaderException::class);
        $this->expectExceptionMessage('Could not extract `host` while parsing the `referer` header');
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);
        $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert');
    }

    public function test_error_thrown_when_invalid_empty_referer_header(): void
    {
        $request = new Request();
        $request->headers->set('referer', '');

        $this->expectException(UnparseableRequestHeaderException::class);
        $this->expectExceptionMessage('Could not extract `host` while parsing the `referer` header');
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);
        $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert');
    }

    public function test_error_thrown_when_invalid_url_referer_header(): void
    {
        $request = new Request();
        $request->headers->set('referer', 'https://:90/abc');

        $this->expectException(UnparseableRequestHeaderException::class);
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);
        $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert');
    }

    public function test_get_absolute_url_using_referer(): void
    {
        $request = new Request();
        $request->headers->set('referer', 'https://www.example.com/some-path');
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->assertEquals('https://www.example.com/path-to-convert', $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert'));
    }

    public function test_get_absolute_url_using_referer_with_default_https_port(): void
    {
        $request = new Request();
        $request->headers->set('referer', 'https://www.example.com:443/some-path');
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->assertEquals('https://www.example.com/path-to-convert', $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert'));
    }

    public function test_get_absolute_url_using_referer_with_default_http_port(): void
    {
        $request = new Request();
        $request->headers->set('referer', 'http://www.example.com:80/some-path');
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->assertEquals('http://www.example.com/path-to-convert', $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert'));
    }

    public function test_get_absolute_url_using_referer_with_alternative_port(): void
    {
        $request = new Request();
        $request->headers->set('referer', 'https://www.example.com:999/some-path');
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->assertEquals('https://www.example.com:999/path-to-convert', $this->refererUrlHelper->getAbsoluteUrl('/path-to-convert'));
    }
}
