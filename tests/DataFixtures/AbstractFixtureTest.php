<?php

namespace Silverback\ApiComponentBundle\Tests\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\DataFixtures\AbstractFixture;

class AbstractFixtureTest extends TestCase
{
    private $abstractFixtureMock;
    private $objectManagerProphecy;

    public function setUp()
    {
        $this->objectManagerProphecy = $this->prophesize(ObjectManager::class);
        $this->abstractFixtureMock = $this->getMockForAbstractClass(AbstractFixture::class);
        $this->abstractFixtureMock->load($this->objectManagerProphecy->reveal());
    }

    public function test_flush_without_entity ()
    {
        $method = new \ReflectionMethod(AbstractFixture::class, 'flush');
        $method->setAccessible(true);

        $this->expectException(\BadMethodCallException::class);
        $method->invoke($this->abstractFixtureMock);
    }
}
