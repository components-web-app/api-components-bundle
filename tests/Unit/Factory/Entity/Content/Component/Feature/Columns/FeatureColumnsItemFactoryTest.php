<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Feature\Columns;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Columns\FeatureColumnsItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class FeatureColumnsItemFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = FeatureColumnsItemFactory::class;
        $this->testOps = [
            'description' => 'dummy',
            'filePath' => null
        ];
        parent::setUp();
    }
}
