<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Feature\TextList;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\TextList\FeatureTextListFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\TextList\FeatureTextListItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class FeatureTextListFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp(array $extraConstructorArgs = [])
    {
        $this->className = FeatureTextListFactory::class;
        $this->testOps = [
            'title' => 'dummy1',
            'columns' => 999
        ];
        $itemFactoryMock = $this
            ->getMockBuilder(FeatureTextListItemFactory::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->extraConstructorArgs = [
            $itemFactoryMock
        ];
        parent::setUp();
    }
}
