<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content;

use Silverback\ApiComponentBundle\Entity\Content\Page;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Factory\Entity\Content\PageFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class PageFactoryTest extends AbstractFactory
{
    protected $presets = ['page'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = PageFactory::class;
        $this->testOps = [
            'title' => 'Page title',
            'metaDescription' => 'Meta',
            'parent' => $this->getMockBuilder(Page::class)->getMock(),
            'layout' => $this->getMockBuilder(Layout::class)->getMock()
        ];
        parent::setUp();
    }
}
