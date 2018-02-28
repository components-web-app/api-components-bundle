<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Gallery;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Gallery\GalleryFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class GalleryFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = GalleryFactory::class;
        $this->testOps = [];
        parent::setUp();
    }
}
