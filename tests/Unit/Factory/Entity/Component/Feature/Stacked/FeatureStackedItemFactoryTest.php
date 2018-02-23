<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Feature\Stacked;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\Stacked\FeatureStackedItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class FeatureStackedItemFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = FeatureStackedItemFactory::class;
        $this->testOps = [
            'description' => 'dummy1',
            'buttonText' => 'dummy2',
            'buttonClass' => 'dummy3'
        ];
        parent::setUp();
    }
}
