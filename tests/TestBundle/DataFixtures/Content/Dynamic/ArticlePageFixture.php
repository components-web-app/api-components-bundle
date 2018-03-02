<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\Dynamic;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Dynamic\ArticlePageFactory;

class ArticlePageFixture extends AbstractFixture
{
    /**
     * @var ArticlePageFactory
     */
    private $articleFactory;

    /**
     * @var string
     */
    private $projectDirectory;

    public function __construct(
        ArticlePageFactory $articleFactory,
        string $projectDirectory
    ) {
        $this->articleFactory = $articleFactory;
        $this->projectDirectory = $projectDirectory;
    }

    public function load(ObjectManager $manager): void
    {
        $this->createArticle();
        $manager->flush();
    }

    private function createArticle()
    {
        return $this->articleFactory->create(
            [
                'title' => 'Article Title',
                'subtitle' => 'Article Subtitle',
                'content' => 'Content',
                'filePath' => $this->projectDirectory . '/public/images/testImage.jpg'
            ]
        );
    }
}
