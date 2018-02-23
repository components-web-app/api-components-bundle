<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Feature\Columns;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\Columns\FeatureColumnsFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class FeatureColumnsFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = FeatureColumnsFactory::class;
        $this->testOps = [
            'columns' => 999,
            'title' => 'dummy'
        ];
        parent::setUp();
    }
}
