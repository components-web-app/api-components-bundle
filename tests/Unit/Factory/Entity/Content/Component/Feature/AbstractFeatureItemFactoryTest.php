<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Feature;

use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\AbstractFeatureItem;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\AbstractFeatureItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class AbstractFeatureItemFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = AbstractFeatureItemFactory::class;
        $this->componentClassName = AbstractFeatureItem::class;
        $this->isFinal = false;
        $this->testOps = [
            'label' => 'dummy1',
            'link' => 'dummy2'
        ];
        parent::setUp();
    }
}
