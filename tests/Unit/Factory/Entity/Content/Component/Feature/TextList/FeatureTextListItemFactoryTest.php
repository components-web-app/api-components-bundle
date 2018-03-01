<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Feature\TextList;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\TextList\FeatureTextListItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class FeatureTextListItemFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = FeatureTextListItemFactory::class;
        $this->testOps = [
            'className' => 'dummy1'
        ];
        parent::setUp();
    }
}
