<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\Component;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\Component\Article\Article;
use Silverback\ApiComponentBundle\Entity\Content\Page;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\ComponentLocationFactory;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\ContentFixture;

class ComponentLocationFixture extends AbstractFixture implements DependentFixtureInterface
{
    /**
     * @var ComponentLocationFactory
     */
    private $componentLocationFactory;

    public function __construct(
        ComponentLocationFactory $componentLocationFactory
    ) {
        $this->componentLocationFactory = $componentLocationFactory;
    }

    public function load(ObjectManager $manager): void
    {
        /** @var Page $childPage */
        $childPage = $this->getReference('childPage');
        /** @var Article $article */
        $article = $this->getReference('article');

        $manager->persist($this->createComponentLocation($article, $childPage));
        $manager->flush();
    }

    private function createComponentLocation(AbstractComponent $component, AbstractContent $content)
    {
        return $this->componentLocationFactory->create(
            [
                'component' => $component,
                'content' => $content
            ]
        );
    }

    public function getDependencies()
    {
        return array(
            ArticleFixture::class,
            ContentFixture::class
        );
    }
}
