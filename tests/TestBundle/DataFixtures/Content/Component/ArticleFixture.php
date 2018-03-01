<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\Component;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Article\ArticleFactory;

class ArticleFixture extends AbstractFixture
{
    /**
     * @var ArticleFactory
     */
    private $articleFactory;

    /**
     * @var string
     */
    private $projectDirectory;

    public function __construct(
        ArticleFactory $articleFactory,
        string $projectDirectory
    ) {
        $this->articleFactory = $articleFactory;
        $this->projectDirectory = $projectDirectory;
    }

    public function load(ObjectManager $manager): void
    {
        $article = $this->createArticle();
        $this->addReference('article', $article);
        $manager->persist($article);
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
