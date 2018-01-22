<?php

namespace Silverback\ApiComponentBundle\Tests\src\DataFixtures\Nav;

use Silverback\ApiComponentBundle\Tests\src\DataFixtures\Page\Navigation\Tabs\TabsNavbarPage;
use Silverback\ApiComponentBundle\Tests\src\DataFixtures\Page\Navigation\Tabs\TabsOne;
use Silverback\ApiComponentBundle\Tests\src\DataFixtures\Page\Navigation\Tabs\TabsTwo;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\CustomEntityInterface;
use Silverback\ApiComponentBundle\DataFixtures\Nav\AbstractNav;
use Silverback\ApiComponentBundle\Entity\Component\Nav\Tabs\Tabs;

class TabsNav extends AbstractNav implements DependentFixtureInterface, CustomEntityInterface
{
    /**
     * @return Tabs
     */
    public function getEntity () {
        return new Tabs();
    }

    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);
        $this->addNavItem('Tab One', 0, $this->getReference('page.navigation.tabs.tab1'));
        $this->addNavItem('Tab Two', 1, $this->getReference('page.navigation.tabs.tab2'));
        $this->addReference('nav.tabs', $this->entity);
        $this->entity->setPage($this->getReference('page.navigation.tabs'));
        $this->flush();
    }

    /**
     * Return page fixtures that this nav uses
     * @return array
     */
    public function getDependencies()
    {
        return [
            TabsOne::class,
            TabsTwo::class,
            TabsNavbarPage::class
        ];
    }
}
