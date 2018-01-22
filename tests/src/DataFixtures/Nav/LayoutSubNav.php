<?php

namespace Silverback\ApiComponentBundle\Tests\src\DataFixtures\Nav;

use Silverback\ApiComponentBundle\Tests\src\DataFixtures\Page\Navigation\Hero\HeroNavbarPage;
use Silverback\ApiComponentBundle\Tests\src\DataFixtures\Page\Navigation\SideMenu\SideMenuPage;
use Silverback\ApiComponentBundle\Tests\src\DataFixtures\Page\Navigation\Tabs\TabsNavbarPage;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Nav\AbstractNav;

class LayoutSubNav extends AbstractNav implements DependentFixtureInterface
{
    /**
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);
        $this->getReference('nav.layout.navs')->setChild($this->entity);
        $this->addNavItem('Hero Navbar', 0, $this->getReference('page.navigation.hero'));
        $this->addNavItem('Tabs', 0, $this->getReference('page.navigation.tabs'));
        $this->addNavItem('Side Menu', 0, $this->getReference('page.navigation.sidemenu'));
        $this->flush();
    }

    /**
     * Return fixtures that this nav uses
     * @return array
     */
    public function getDependencies()
    {
        return [
            LayoutNav::class,
            HeroNavbarPage::class,
            TabsNavbarPage::class,
            SideMenuPage::class
        ];
    }
}