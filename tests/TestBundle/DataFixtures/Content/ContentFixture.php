<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\Component\Content\Content;
use Silverback\ApiComponentBundle\Entity\Content\Page;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Entity\Route\Route;
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

        $parentPage = $this->createPage(null, null, new Route('/'));
        $childPage = $this->createPage($parentPage, $layout);
        $this->addReference('childPage', $childPage);

        /** @var Content $content */
        $content = $this->getReference('content');
        $this->createComponentGroup($content);

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

    private function createPage(Page $parent = null, Layout $layout = null, Route $route = null)
    {
        return $this->pageFactory->create(
            [
                'title' => 'Page title',
                'metaDescription' => 'Meta description',
                'parent' => $parent,
                'layout' => $layout,
                'route' => $route
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