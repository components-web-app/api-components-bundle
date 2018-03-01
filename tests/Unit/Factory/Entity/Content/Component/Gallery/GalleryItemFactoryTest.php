<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Gallery;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Gallery\GalleryItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class GalleryItemFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = GalleryItemFactory::class;
        $this->testOps = [
            'title' => 'Gallery Item Title',
            'caption' => 'Gallery Item Caption',
            'filePath' => '/public/images/testImage.jpg'
        ];
        parent::setUp();
    }
}
