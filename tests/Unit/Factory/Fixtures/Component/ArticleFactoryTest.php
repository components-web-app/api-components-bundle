<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Fixtures\Component;

use Doctrine\Common\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Factory\Fixtures\Component\ArticleFactory;

class ArticleFactoryTest extends TestCase
{
    /**
     * @var ArticleFactory
     */
    private $componentFactory;

    public function setUp()
    {
        /** @var ObjectManager $objectManagerMock */
        $objectManagerMock = $this
            ->getMockBuilder(ObjectManager::class)
            ->getMock()
        ;

        $this->componentFactory = new ArticleFactory($objectManagerMock);
    }

    public function test_create()
    {
        $component = $this->componentFactory->create(
            [
                'title' => 'Title',
                'subtitle' => 'Subtitle',
                'content' => '<p>Some content</p>',
                'filePath' => '/images/testImage.jpg'
            ]
        );
        $this->assertEquals('Title', $component->getTitle());
        $this->assertEquals('Subtitle', $component->getSubtitle());
        $this->assertEquals('<p>Some content</p>', $component->getContent());
        $this->assertEquals('/images/testImage.jpg', $component->getFilePath());
    }
}
