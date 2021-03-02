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

namespace Silverback\ApiComponentsBundle\Tests\Serializer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SerializeFormatResolverTest extends TestCase
{
    /**
     * @var MockObject|RequestStack
     */
    private MockObject $requestStackMock;

    private SerializeFormatResolver $formatResolver;

    protected function setUp(): void
    {
        $this->requestStackMock = $this->createMock(RequestStack::class);
        $this->formatResolver = new SerializeFormatResolver($this->requestStackMock, 'default_format');
    }

    public function test_get_format_from_attribute(): void
    {
        $request = new Request();
        $request->attributes->set('_format', 'attr_format');
        $this->assertEquals('attr_format', $this->formatResolver->getFormatFromRequest($request));
    }

    public function test_get_format_from_content_type_header(): void
    {
        $request = new Request();
        $request->headers->set('CONTENT_TYPE', 'text/xml');
        $this->assertEquals('xml', $this->formatResolver->getFormatFromRequest($request));
    }

    public function test_get_default_format_if_content_type_header_invalid(): void
    {
        $request = new Request();
        $request->headers->set('CONTENT_TYPE', 'invalid/content_type');
        $this->assertEquals('default_format', $this->formatResolver->getFormatFromRequest($request));
    }

    public function test_get_format_from_request_fallback(): void
    {
        $request = new Request();
        $this->assertEquals('default_format', $this->formatResolver->getFormatFromRequest($request));
    }

    public function test_default_format_if_no_master_request(): void
    {
        $this->requestStackMock
            ->expects(self::once())
            ->method('getMasterRequest')
            ->willReturn(null);

        $this->assertEquals('default_format', $this->formatResolver->getFormat());
    }

    public function test_format_from_master_request(): void
    {
        $request = new Request();
        $request->attributes->set('_format', 'attr_format');

        $this->requestStackMock
            ->expects(self::once())
            ->method('getMasterRequest')
            ->willReturn($request);

        $this->assertEquals($this->formatResolver->getFormatFromRequest($request), $this->formatResolver->getFormat());
    }
}
