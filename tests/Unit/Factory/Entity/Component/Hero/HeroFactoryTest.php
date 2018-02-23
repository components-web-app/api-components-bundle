<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Hero;

use Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs\Tabs;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Hero\HeroFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class HeroFactoryTest extends AbstractFactory
{
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
