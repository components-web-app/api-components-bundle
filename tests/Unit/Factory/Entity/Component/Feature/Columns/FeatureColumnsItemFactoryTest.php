<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Feature\Columns;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\Columns\FeatureColumnsItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class FeatureColumnsItemFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = FeatureColumnsItemFactory::class;
        $this->testOps = [
            'description' => 'dummy'
        ];
        parent::setUp();
    }
}
