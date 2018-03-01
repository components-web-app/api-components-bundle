<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Route;

use Cocur\Slugify\SlugifyInterface;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Factory\Entity\Route\RouteFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class RouteFactoryTest extends AbstractFactory
{
    public function getConstructorArgs(): array
    {
        $args = parent::getConstructorArgs();
        $args[] = $this->getMockBuilder(SlugifyInterface::class)->getMock();
        return $args;
    }

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
