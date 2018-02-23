<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Feature\TextList;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\TextList\FeatureTextListFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class FeatureTextListFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = FeatureTextListFactory::class;
        $this->testOps = [
            'title' => 'dummy1'
        ];
        parent::setUp();
    }
}
