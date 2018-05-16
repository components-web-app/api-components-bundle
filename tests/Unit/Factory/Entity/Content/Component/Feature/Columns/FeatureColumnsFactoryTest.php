<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Feature\Columns;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Columns\FeatureColumnsFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Columns\FeatureColumnsItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class FeatureColumnsFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = FeatureColumnsFactory::class;
        $this->testOps = [
            'title' => 'dummy'
        ];
        $itemFactoryMock = $this
            ->getMockBuilder(FeatureColumnsItemFactory::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->extraConstructorArgs = [
            $itemFactoryMock
        ];
        parent::setUp();
    }
}
