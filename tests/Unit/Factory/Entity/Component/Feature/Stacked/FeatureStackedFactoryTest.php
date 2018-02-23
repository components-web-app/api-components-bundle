<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Feature\Stacked;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\Stacked\FeatureStackedFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class FeatureStackedFactoryTest extends AbstractFactory
{
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
