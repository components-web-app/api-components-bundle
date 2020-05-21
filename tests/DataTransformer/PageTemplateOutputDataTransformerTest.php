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

namespace Silverback\ApiComponentsBundle\Tests\DataTransformer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataTransformer\PageOutputDataTransformer;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Repository\Core\LayoutRepository;

class PageTemplateOutputDataTransformerTest extends TestCase
{
    /**
     * @var MockObject|LayoutRepository
     */
    private $respositoryMock;

    private PageOutputDataTransformer $transformer;

    protected function setUp(): void
    {
        $this->respositoryMock = $this->createMock(LayoutRepository::class);
        $this->transformer = new PageOutputDataTransformer($this->respositoryMock);
    }

    public function test_supports_method(): void
    {
        $supported = new Page();
        $supported->layout = null;
        $this->assertTrue($this->transformer->supportsTransformation($supported, '', []));

        $unsupported = new Page();
        $unsupported->layout = new Layout();
        $this->assertFalse($this->transformer->supportsTransformation($unsupported, '', []));
        $this->assertFalse(
            $this->transformer->supportsTransformation(
                new class() {
                },
                '',
                []
            )
        );
    }

    public function test_default_layout_added(): void
    {
        $layout = new Layout();
        $layout->default = true;

        $this->respositoryMock
            ->expects($this->once())
            ->method('findDefault')
            ->willReturn($layout);

        $supported = new Page();
        $output = $this->transformer->transform($supported, '', []);
        $this->assertEquals($layout, $output->layout);
    }
}
