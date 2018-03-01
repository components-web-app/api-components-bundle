<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Route;

use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Factory\Entity\Route\RouteFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class RouteFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = RouteFactory::class;
        $this->testOps = [
            'route' => '/dummy-route',
            'content' => $this->getMockForAbstractClass(AbstractContent::class),
            'redirect' => $this->getMockBuilder(Route::class)->getMock()
        ];
        parent::setUp();
    }
}
