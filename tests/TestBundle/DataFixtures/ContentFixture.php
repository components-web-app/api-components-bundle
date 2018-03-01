<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\NavBar\NavBar;
use Silverback\ApiComponentBundle\Entity\Content\Page;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Article\ArticleFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\ComponentLocationFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Content\ContentFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Columns\FeatureColumnsFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Stacked\FeatureStackedFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\TextList\FeatureTextListFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Form\FormFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Gallery\GalleryFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Gallery\GalleryItemFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Hero\HeroFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Menu\MenuFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Menu\MenuItemFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\NavBar\NavBarFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\NavBar\NavBarItemFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Tabs\TabsFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Tabs\TabsItemFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\ComponentGroupFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\PageFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Layout\LayoutFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Route\RouteFactory;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;

class ContentFixture extends AbstractFixture
{
    public const DUMMY_CONTENT = 'DUMMY CONTENT';

    /**
     * @var ArticleFactory
     */
    private $articleFactory;
    /**
     * @var ContentFactory
     */
    private $contentFactory;
    /**
     * @var FormFactory
     */
    private $formFactory;
    /**
     * @var FeatureColumnsFactory
     */
    private $featureColumnsFactory;
    /**
     * @var FeatureStackedFactory
     */
    private $featureStackedFactory;
    /**
     * @var FeatureTextListFactory
     */
    private $featureTextListFactory;
    /**
     * @var GalleryFactory
     */
    private $galleryFactory;
    /**
     * @var GalleryItemFactory
     */
    private $galleryItemFactory;
    /**
     * @var HeroFactory
     */
    private $heroFactory;
    /**
     * @var MenuFactory
     */
    private $menuFactory;
    /**
     * @var MenuItemFactory
     */
    private $menuItemFactory;
    /**
     * @var NavBarFactory
     */
    private $navBarFactory;
    /**
     * @var NavBarItemFactory
     */
    private $navBarItemFactory;
    /**
     * @var TabsFactory
     */
    private $tabsFactory;
    /**
     * @var TabsItemFactory
     */
    private $tabsItemFactory;
    /**
     * @var ComponentLocationFactory
     */
    private $componentLocationFactory;
    /**
     * @var ComponentGroupFactory
     */
    private $componentGroupFactory;
    /**
     * @var PageFactory
     */
    private $pageFactory;
    /**
     * @var LayoutFactory
     */
    private $layoutFactory;
    /**
     * @var RouteFactory
     */
    private $routeFactory;

    /**
     * @var string
     */
    private $projectDirectory;

    public function __construct(
        ArticleFactory $articleFactory,
        ContentFactory $contentFactory,
        FeatureColumnsFactory $featureColumnsFactory,
        FeatureStackedFactory $featureStackedFactory,
        FeatureTextListFactory $featureTextListFactory,
        FormFactory $formFactory,
        GalleryFactory $galleryFactory,
        GalleryItemFactory $galleryItemFactory,
        HeroFactory $heroFactory,
        MenuFactory $menuFactory,
        MenuItemFactory $menuItemFactory,
        NavBarFactory $navBarFactory,
        NavBarItemFactory $navBarItemFactory,
        TabsFactory $tabsFactory,
        TabsItemFactory $tabsItemFactory,
        ComponentLocationFactory $componentLocationFactory,
        ComponentGroupFactory $componentGroupFactory,
        PageFactory $pageFactory,
        LayoutFactory $layoutFactory,
        RouteFactory $routeFactory,
        string $projectDirectory
    ) {
        $this->articleFactory = $articleFactory;
        $this->contentFactory = $contentFactory;
        $this->featureColumnsFactory = $featureColumnsFactory;
        $this->featureStackedFactory = $featureStackedFactory;
        $this->featureTextListFactory = $featureTextListFactory;
        $this->formFactory = $formFactory;
        $this->galleryFactory = $galleryFactory;
        $this->galleryItemFactory = $galleryItemFactory;
        $this->heroFactory = $heroFactory;
        $this->menuFactory = $menuFactory;
        $this->menuItemFactory = $menuItemFactory;
        $this->navBarFactory = $navBarFactory;
        $this->navBarItemFactory = $navBarItemFactory;
        $this->tabsFactory = $tabsFactory;
        $this->tabsItemFactory = $tabsItemFactory;
        $this->componentLocationFactory = $componentLocationFactory;
        $this->componentGroupFactory = $componentGroupFactory;
        $this->pageFactory = $pageFactory;
        $this->layoutFactory = $layoutFactory;
        $this->routeFactory = $routeFactory;
        $this->projectDirectory = $projectDirectory;
    }

    public function load(ObjectManager $manager): void
    {
        $article = $this->createArticle();
        $manager->persist($article);
        $manager->persist($this->createContent());
        $manager->persist($this->createFeatureColumns());
        $manager->persist($this->createFeatureStacked());
        $manager->persist($this->createFeatureTextList());
        $manager->persist($this->createForm());
        $manager->persist($this->createGallery());
        $manager->persist($this->createGalleryItem());
        $manager->persist($this->createHero());
        $manager->persist($this->createMenu());
        $manager->persist($this->createMenuItem());
        $manager->persist($this->createNavBar());
        $manager->persist($this->createNavBarItem());
        $manager->persist($this->createTabs());
        $manager->persist($this->createTabsItem());

        $layout = $this->createLayout();
        $manager->persist($layout);
        $parentPage = $this->createPage();
        $manager->persist($parentPage);
        $childPage = $this->createPage($parentPage, $layout);
        $manager->persist($childPage);

        $manager->persist($this->createComponentLocation($article, $childPage));
        $manager->persist($this->createComponentGroup($article));

        $manager->persist($this->createRoute('/child', $childPage));
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

    private function createContent()
    {
        return $this->contentFactory->create(
            [
                'content' => self::DUMMY_CONTENT
            ]
        );
    }

    private function createForm()
    {
        return $this->formFactory->create(
            [
                'formType' => TestType::class,
                'successHandler' => TestHandler::class
            ]
        );
    }

    private function createFeatureColumns()
    {
        return $this->featureColumnsFactory->create(
            [
                'columns' => 3,
                'title' => 'Column features title'
            ]
        );
    }

    private function createFeatureStacked()
    {
        return $this->featureStackedFactory->create(
            [
                'reverse' => true
            ]
        );
    }

    private function createFeatureTextList()
    {
        return $this->featureTextListFactory->create(
            [
                'title' => 'Text list features title'
            ]
        );
    }

    private function createGallery()
    {
        return $this->galleryFactory->create();
    }

    private function createGalleryItem()
    {
        return $this->galleryItemFactory->create(
            [
                'title' => 'Gallery Item Title',
                'caption' => 'Item Caption',
                'filePath' => '/public/images/testImage.jpg'
            ]
        );
    }

    private function createHero()
    {
        return $this->heroFactory->create(
            [
                'title' => 'Hero Title',
                'subtitle' => 'Hero Subtitle',
                'tabs' => null
            ]
        );
    }

    private function createMenu()
    {
        return $this->menuFactory->create();
    }

    private function createMenuItem()
    {
        return $this->menuItemFactory->create(
            [
                'label' => 'Dummy label',
                'menuLabel' => false
            ]
        );
    }

    private function createNavBar()
    {
        return $this->navBarFactory->create();
    }

    private function createNavBarItem()
    {
        return $this->navBarItemFactory->create(
            [
                'label' => 'Dummy label'
            ]
        );
    }

    private function createTabs()
    {
        return $this->tabsFactory->create();
    }

    private function createTabsItem()
    {
        return $this->tabsItemFactory->create(
            [
                'label' => 'Dummy label'
            ]
        );
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

    private function createComponentGroup(AbstractComponent $component)
    {
        return $this->componentGroupFactory->create(
            [
                'parent' => $component
            ]
        );
    }

    private function createPage(Page $page = null, Layout $layout = null)
    {
        return $this->pageFactory->create(
            [
                'title' => 'Page title',
                'metaDescription' => 'Meta description',
                'parent' => $page,
                'layout' => $layout
            ]
        );
    }

    private function createLayout(bool $default = false, NavBar $navBar = null)
    {
        return $this->layoutFactory->create(
            [
                'default' => $default,
                'navBar' => $navBar
            ]
        );
    }

    private function createRoute(string $route, RouteAwareInterface $content, Route $redirect = null)
    {
        return $this->routeFactory->create(
            [
                'route' => $route,
                'content' => $content,
                'redirect' => $redirect
            ]
        );
    }
}
