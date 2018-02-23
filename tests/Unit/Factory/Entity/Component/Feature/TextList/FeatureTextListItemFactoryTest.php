<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Feature\TextList;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\TextList\FeatureTextListItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class FeatureTextListItemFactoryTest extends AbstractFactory
{
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
