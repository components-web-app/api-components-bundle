<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Article;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Article\ArticleFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class ArticleFactoryTest extends AbstractFactory
{
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
