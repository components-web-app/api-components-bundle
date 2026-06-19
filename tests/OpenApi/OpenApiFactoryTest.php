<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\OpenApi;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\OpenApi\OpenApiFactory;

class OpenApiFactoryTest extends TestCase
{
    public function testGetExtendedVersionAppendsParenthesizedBundleVersion(): void
    {
        $extended = OpenApiFactory::getExtendedVersion('3.1.0');

        $this->assertStringStartsWith('3.1.0 (', $extended);
        $this->assertStringEndsWith(')', $extended);
    }

    public function testGetExtendedVersionPreservesOriginalVersion(): void
    {
        $extended = OpenApiFactory::getExtendedVersion('2.0');

        $this->assertStringStartsWith('2.0', $extended);
    }
}
