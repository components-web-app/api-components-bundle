<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Article;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Article\ArticleFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class ArticleFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = ArticleFactory::class;
        $this->testOps = [
            'title' => 'Title',
            'subtitle' => 'Subtitle',
            'content' => '<p>Some content</p>',
            'filePath' => '/images/testImage.jpg'
        ];
        parent::setUp();
    }
}
