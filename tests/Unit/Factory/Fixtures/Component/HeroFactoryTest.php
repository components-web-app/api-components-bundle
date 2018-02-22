<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Fixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs\Tabs;
use Silverback\ApiComponentBundle\Exception\InvalidFactoryOptionException;
use Silverback\ApiComponentBundle\Factory\Fixtures\Component\HeroFactory;

class HeroFactoryTest extends AbstractFactoryTest
{
    /**
     * @var HeroFactory
     */
    private $componentFactory;

    public function setUp()
    {
        $this->componentFactory = new HeroFactory(...$this->getConstructorArgs());
    }

    public function test_invalid_option()
    {
        $this->expectException(InvalidFactoryOptionException::class);
        $this->componentFactory->create(
            [
                'invalid' => null
            ]
        );
    }

    public function test_create()
    {
        $tabs = new Tabs();
        $ops = [
            'title' => 'Title',
            'subtitle' => 'Subtitle',
            'tabs' => $tabs
        ];
        $component = $this->componentFactory->create($ops);
        $this->assertEquals($ops['title'], $component->getTitle());
        $this->assertEquals($ops['subtitle'], $component->getSubtitle());
        $this->assertEquals($ops['tabs'], $component->getTabs());
    }
}
