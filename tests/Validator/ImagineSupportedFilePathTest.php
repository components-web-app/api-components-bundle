<?php

declare(strict_types=1);

namespace Validator;

use Silverback\ApiComponentBundle\Validator\ImagineSupportedFilePath;
use PHPUnit\Framework\TestCase;

class ImagineSupportedFilePathTest extends TestCase
{
    public function testIsValidFilePath(): void
    {
        $basePath = __DIR__ . '/../Functional/TestBundle/Resources/';
        $this->assertFalse(ImagineSupportedFilePath::isValidFilePath(null));
        $this->assertFalse(ImagineSupportedFilePath::isValidFilePath('/invalid/path'));
        $this->assertFalse(ImagineSupportedFilePath::isValidFilePath($basePath . 'not_an_image.txt'));
        $this->assertFalse(ImagineSupportedFilePath::isValidFilePath($basePath. 'image.svg'));

        $this->assertTrue(ImagineSupportedFilePath::isValidFilePath($basePath. 'image.jpg'));
        $this->assertTrue(ImagineSupportedFilePath::isValidFilePath($basePath. 'image.jpf'));
        $this->assertTrue(ImagineSupportedFilePath::isValidFilePath($basePath. 'image.png'));
        $this->assertTrue(ImagineSupportedFilePath::isValidFilePath($basePath. 'image.gif'));
    }
}
