<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Factory\Uploadable;

use ApiPlatform\Metadata\IriConverterInterface;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Factory\Uploadable\ApiUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;

class ApiUrlGeneratorTest extends TestCase
{
    private function buildUrlHelper(string $baseUrl = 'https://example.com'): UrlHelper
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create($baseUrl));

        return new UrlHelper($requestStack);
    }

    public function testGeneratesDownloadUrlFromResourceIriAndPropertyName(): void
    {
        $object = new \stdClass();

        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->with($object)->willReturn('/_/component_groups/abc-123');

        $generator = new ApiUrlGenerator($iriConverter, $this->buildUrlHelper());

        $result = $generator->generateUrl($object, 'fileName', $this->createMock(Filesystem::class), '/path/to/file.png');

        $this->assertSame('https://example.com/_/component_groups/abc-123/download/file_name', $result);
    }

    public function testConvertsPropertyNameToSnakeCase(): void
    {
        $object = new \stdClass();

        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/resource/1');

        $generator = new ApiUrlGenerator($iriConverter, $this->buildUrlHelper());

        $result = $generator->generateUrl($object, 'myUploadedFile', $this->createMock(Filesystem::class), 'file.png');

        $this->assertStringEndsWith('/download/my_uploaded_file', $result);
    }
}
