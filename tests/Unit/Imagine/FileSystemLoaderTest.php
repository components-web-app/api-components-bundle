<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Imagine;

use Liip\ImagineBundle\Binary\Locator\LocatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Imagine\FileSystemLoader;
use Symfony\Component\HttpFoundation\File\MimeType\ExtensionGuesserInterface;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;

class FileSystemLoaderTest extends TestCase
{
    /**
     * @var FileSystemLoader
     */
    private $fileSystemLoader;

    /**
     * @var string
     */
    private $rootPath = '/root/path';

    public function setUp()
    {
        /** @var MockObject|MimeTypeGuesserInterface $mimeTypeGuesserInterfaceMock */
        $mimeTypeGuesserInterfaceMock = $this->getMockBuilder(MimeTypeGuesserInterface::class)->getMock();
        /** @var MockObject|ExtensionGuesserInterface $extensionGetterInterfaceMock */
        $extensionGetterInterfaceMock = $this->getMockBuilder(ExtensionGuesserInterface::class)->getMock();
        /** @var MockObject|LocatorInterface $locationInterfaceMock */
        $locationInterfaceMock = $this->getMockBuilder(LocatorInterface::class)->getMock();

        $this->fileSystemLoader = new FileSystemLoader(
            $mimeTypeGuesserInterfaceMock,
            $extensionGetterInterfaceMock,
            $locationInterfaceMock,
            [$this->rootPath]
        );
    }

    public function test_getImaginePath()
    {
        $relativePath = '/image/path.jpg';
        $this->assertEquals($relativePath, $this->fileSystemLoader->getImaginePath($this->rootPath . $relativePath));
        $this->assertEquals($relativePath, $this->fileSystemLoader->getImaginePath($relativePath));
    }
}
