<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Hero;

use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\Tabs\Tabs;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Hero\HeroFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class HeroFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $tabs = new Tabs();
        $this->className = HeroFactory::class;
        $this->testOps = [
            'title' => 'Title',
            'subtitle' => 'Subtitle',
            'tabs' => $tabs
        ];
        parent::setUp();
    }
}
