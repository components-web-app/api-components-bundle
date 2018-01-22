<?php

namespace Silverback\ApiComponentBundle\Tests\src\DataFixtures\Nav;

use Silverback\ApiComponentBundle\Tests\src\DataFixtures\Page\Navigation\SideMenu\SideMenuPage;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\CustomEntityInterface;
use Silverback\ApiComponentBundle\DataFixtures\Nav\AbstractNav;
use Silverback\ApiComponentBundle\Entity\Component\Nav\Menu\Menu;

class SideMenuNav extends AbstractNav implements DependentFixtureInterface, CustomEntityInterface
{
    /**
     * @return Menu
     */
    public function getEntity () {
        return new Menu();
    }

    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);
        $this->addNavItem('Menu One', 0, $this->getReference('page.navigation.sidemenu'), 'frag1');
        $this->addNavItem('Menu Two', 1, $this->getReference('page.navigation.sidemenu'), 'frag2');
        $this->addReference('nav.sidemenu', $this->entity);
        $this->entity->setPage($this->getReference('page.navigation.sidemenu'));
        $this->flush();
    }

    /**
     * Return page fixtures that this nav uses
     * @return array
     */
    public function getDependencies()
    {
        return [
            SideMenuPage::class
        ];
    }
}
