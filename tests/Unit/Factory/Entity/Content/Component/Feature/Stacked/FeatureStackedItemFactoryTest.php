<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Feature\Stacked;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Stacked\FeatureStackedItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class FeatureStackedItemFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = FeatureStackedItemFactory::class;
        $this->testOps = [
            'description' => 'dummy1',
            'buttonText' => 'dummy2',
            'buttonClass' => 'dummy3',
            'filePath' => null
        ];
        parent::setUp();
    }
}
