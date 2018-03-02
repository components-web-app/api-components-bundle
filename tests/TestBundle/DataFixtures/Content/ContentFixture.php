<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\Component\Content\Content;
use Silverback\ApiComponentBundle\Entity\Content\Page;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Factory\Entity\Content\ComponentGroupFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\PageFactory;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\Dynamic\ArticlePageFixture;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Layout\LayoutFixture;

class ContentFixture extends AbstractFixture implements DependentFixtureInterface
{
    /**
     * @var ComponentGroupFactory
     */
    private $componentGroupFactory;
    /**
     * @var PageFactory
     */
    private $pageFactory;

    public function __construct(
        ComponentGroupFactory $componentGroupFactory,
        PageFactory $pageFactory
    ) {
        $this->componentGroupFactory = $componentGroupFactory;
        $this->pageFactory = $pageFactory;
    }

    public function load(ObjectManager $manager): void
    {
        /** @var Layout $article */
        $layout = $this->getReference('layout');

        $parentPage = $this->createPage();
        $manager->persist($parentPage);
        $childPage = $this->createPage($parentPage, $layout);
        $manager->persist($childPage);
        $this->addReference('childPage', $childPage);

        /** @var Content $content */
        $content = $this->getReference('content');
        $manager->persist($this->createComponentGroup($content));

        $manager->flush();
    }

    private function createComponentGroup(AbstractComponent $component)
    {
        return $this->componentGroupFactory->create(
            [
                'parent' => $component
            ]
        );
    }

    private function createPage(Page $parent = null, Layout $layout = null)
    {
        return $this->pageFactory->create(
            [
                'title' => 'Page title',
                'metaDescription' => 'Meta description',
                'parent' => $parent,
                'layout' => $layout
            ]
        );
    }

    public function getDependencies()
    {
        return array(
            ArticlePageFixture::class,
            LayoutFixture::class
        );
    }
}
