<?php

namespace Silverback\ApiComponentBundle\Tests\Factory\Component;

use Doctrine\Common\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\Component\Content\Content;
use Silverback\ApiComponentBundle\Entity\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\Page;
use Silverback\ApiComponentBundle\Factory\Component\AbstractComponentFactory;

class AbstractComponentTest extends TestCase
{
    private $abstractComponentMock;
    private $dummyComponent;
    private $objectManagerProphecy;

    public function setUp ()
    {
        $objectManagerProphecy = $this->prophesize(ObjectManager::class);
        $this->abstractComponentMock = $this->getMockForAbstractClass(AbstractComponentFactory::class, [
            $objectManagerProphecy->reveal()
        ]);
        $this->objectManagerProphecy = $objectManagerProphecy;
        $this->dummyComponent = new Content();
    }

    public function test_abstract_component_has_correct_default_options ()
    {
        $this->assertEquals(AbstractComponentFactory::defaultOps(), [
            'className' => null
        ]);
    }

    public function test_options_setting_and_unknown_options_stripped ()
    {
        $ops = $this->abstractComponentMock->processOps(
            [
                'className' => 'class',
                'unknownOption' => 'something'
            ]
        );
        $this->assertEquals($ops, [
            'className' => 'class'
        ]);
    }

    public function test_create_component_for_page_and_class_name_set ()
    {
        $this->abstractComponentMock
            ->expects($this->once())
            ->method('getComponent')
            ->will($this->returnValue($this->dummyComponent))
        ;

        $owner = new Page();
        $component = $this->abstractComponentMock->create($owner, [ 'className' => 'myClass' ]);
        $this->assertEquals($component->getClassName(), 'myClass');
        $this->assertEquals($component->getPage(), $owner);
    }

    public function test_create_component_for_component_group_and_class_name_null ()
    {
        $this->abstractComponentMock
            ->expects($this->once())
            ->method('getComponent')
            ->will($this->returnValue($this->dummyComponent))
        ;

        $owner = new ComponentGroup();
        $component = $this->abstractComponentMock->create($owner, null);
        $this->assertEquals($component->getClassName(), null);
        $this->assertEquals($component->getGroup(), $owner);
    }

    public function test_create_component_with_invalid_owner ()
    {
        $this->abstractComponentMock
            ->expects($this->once())
            ->method('getComponent')
            ->will($this->returnValue($this->dummyComponent))
        ;

        $owner = new Content();
        $this->expectException(\InvalidArgumentException::class);
        $this->abstractComponentMock->create($owner, null);
    }
}
