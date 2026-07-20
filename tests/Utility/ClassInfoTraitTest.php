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

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Utility\ClassInfoTrait;

class ClassInfoTraitTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        $this->subject = new class {
            use ClassInfoTrait;

            public function real(string $className): string
            {
                return $this->getRealClassName($className);
            }
        };
    }

    public function test_plain_class_name_is_returned_unchanged(): void
    {
        self::assertSame('App\\Entity\\Foo', $this->subject->real('App\\Entity\\Foo'));
    }

    public function test_doctrine_cg_proxy_marker_is_stripped(): void
    {
        // Kills LogicalAnd (line 41): with a '__CG__' marker present, `false === $positionCg` is false,
        // so the early "return unchanged" must NOT fire — the real class name is extracted instead. The
        // `||` mutant would return the proxy name unchanged.
        self::assertSame('App\\Entity\\Foo', $this->subject->real('Proxies\\__CG__\\App\\Entity\\Foo'));
    }

    public function test_ocramius_pm_proxy_marker_is_stripped(): void
    {
        // Exercises the '__PM__' branch: the real class name sits between the marker and the trailing
        // proxy-id segment.
        self::assertSame('App\\Entity\\Foo', $this->subject->real('MyProxies\\__PM__\\App\\Entity\\Foo\\abc123'));
    }
}
