<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Feature\Stacked;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Stacked\FeatureStackedFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class FeatureStackedFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = FeatureStackedFactory::class;
        $this->testOps = [
            'reverse' => true
        ];
        parent::setUp();
    }
}
