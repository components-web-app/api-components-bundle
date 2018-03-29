<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Collection;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Collection\CollectionFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class CollectionFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = CollectionFactory::class;
        $this->testOps = [
            'resource' => '',
            'perPage' => 28
        ];
        parent::setUp();
    }
}
