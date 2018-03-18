<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Feature\Columns;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Columns\FeatureColumnsFactory;
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
        parent::setUp();
    }
}
