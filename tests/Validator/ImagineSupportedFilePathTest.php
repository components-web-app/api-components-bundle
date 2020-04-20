<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Validator\ImagineSupportedFilePath;

class ImagineSupportedFilePathTest extends TestCase
{
    public function test_is_valid_file_path(): void
    {
        $basePath = __DIR__ . '/../Functional/TestBundle/Resources/';
        $this->assertFalse(ImagineSupportedFilePath::isValidFilePath(null));
        $this->assertFalse(ImagineSupportedFilePath::isValidFilePath('/invalid/path'));
        $this->assertFalse(ImagineSupportedFilePath::isValidFilePath($basePath . 'not_an_image.txt'));
        $this->assertFalse(ImagineSupportedFilePath::isValidFilePath($basePath . 'image.svg'));

        $this->assertTrue(ImagineSupportedFilePath::isValidFilePath($basePath . 'image.jpg'));
        $this->assertTrue(ImagineSupportedFilePath::isValidFilePath($basePath . 'image.jpf'));
        $this->assertTrue(ImagineSupportedFilePath::isValidFilePath($basePath . 'image.png'));
        $this->assertTrue(ImagineSupportedFilePath::isValidFilePath($basePath . 'image.gif'));
    }
}
