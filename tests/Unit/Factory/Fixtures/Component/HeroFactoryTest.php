<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Fixtures\Component;

use Doctrine\Common\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs\Tabs;
use Silverback\ApiComponentBundle\Factory\Fixtures\Component\HeroFactory;

class HeroFactoryTest extends TestCase
{
    /**
     * @var HeroFactory
     */
    private $componentFactory;

    public function setUp()
    {
        /** @var ObjectManager $objectManagerMock */
        $objectManagerMock = $this
            ->getMockBuilder(ObjectManager::class)
            ->getMock()
        ;

        $this->componentFactory = new HeroFactory($objectManagerMock);
    }

    public function test_create()
    {
        $tabs = new Tabs();
        $component = $this->componentFactory->create(
            [
                'title' => 'Title',
                'subtitle' => 'Subtitle',
                'tabs' => $tabs
            ]
        );
        $this->assertEquals('Title', $component->getTitle());
        $this->assertEquals('Subtitle', $component->getSubtitle());
        $this->assertEquals($tabs, $component->getTabs());
    }
}
