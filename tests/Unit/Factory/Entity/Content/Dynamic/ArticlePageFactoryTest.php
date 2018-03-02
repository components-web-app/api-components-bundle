<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Dynamic;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Dynamic\ArticlePageFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class ArticlePageFactoryTest extends AbstractFactory
{
    protected $presets = ['page'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = ArticlePageFactory::class;
        $this->testOps = [
            'title' => 'Title',
            'subtitle' => 'Subtitle',
            'content' => '<p>Some content</p>',
            'filePath' => '/images/testImage.jpg'
        ];
        parent::setUp();
    }
}
