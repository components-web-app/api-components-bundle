<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Fixtures\Component;

use Silverback\ApiComponentBundle\Exception\InvalidFactoryOptionException;
use Silverback\ApiComponentBundle\Factory\Fixtures\Component\ArticleFactory;

class ArticleFactoryTest extends AbstractFactoryTest
{
    /**
     * @var ArticleFactory
     */
    private $componentFactory;

    public function setUp()
    {
        $this->componentFactory = new ArticleFactory(...$this->getConstructorArgs());
    }

    public function test_invalid_option()
    {
        $this->expectException(InvalidFactoryOptionException::class);
        $this->componentFactory->create(
            [
                'invalid' => null
            ]
        );
    }

    public function test_create()
    {
        $ops = [
            'title' => 'Title',
            'subtitle' => 'Subtitle',
            'content' => '<p>Some content</p>',
            'filePath' => '/images/testImage.jpg'
        ];
        $component = $this->componentFactory->create($ops);
        $this->assertEquals($ops['title'], $component->getTitle());
        $this->assertEquals($ops['subtitle'], $component->getSubtitle());
        $this->assertEquals($ops['content'], $component->getContent());
        $this->assertEquals($ops['filePath'], $component->getFilePath());
    }
}
