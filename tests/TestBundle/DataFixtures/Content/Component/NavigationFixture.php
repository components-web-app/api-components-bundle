<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\Component;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Menu\MenuFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Menu\MenuItemFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\NavBar\NavBarFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\NavBar\NavBarItemFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Tabs\TabsFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Tabs\TabsItemFactory;

class NavigationFixture extends AbstractFixture
{
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

    public function __construct(
        MenuFactory $menuFactory,
        MenuItemFactory $menuItemFactory,
        NavBarFactory $navBarFactory,
        NavBarItemFactory $navBarItemFactory,
        TabsFactory $tabsFactory,
        TabsItemFactory $tabsItemFactory
    ) {
        $this->menuFactory = $menuFactory;
        $this->menuItemFactory = $menuItemFactory;
        $this->navBarFactory = $navBarFactory;
        $this->navBarItemFactory = $navBarItemFactory;
        $this->tabsFactory = $tabsFactory;
        $this->tabsItemFactory = $tabsItemFactory;
    }

    public function load(ObjectManager $manager): void
    {
        $manager->persist($this->createMenu());
        $manager->persist($this->createMenuItem());
        $manager->persist($this->createNavBar());
        $manager->persist($this->createNavBarItem());
        $manager->persist($this->createTabs());
        $manager->persist($this->createTabsItem());
        $manager->flush();
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
}
