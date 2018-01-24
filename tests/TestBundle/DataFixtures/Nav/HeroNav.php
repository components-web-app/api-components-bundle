<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Nav;

use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page\Navigation\Hero\HeroNavbarPage;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page\Navigation\Hero\HeroOne;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page\Navigation\Hero\HeroTwo;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Nav\AbstractNav;

class HeroNav extends AbstractNav implements DependentFixtureInterface
{
    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);
        $this->addNavItem('Hero One', 0, $this->getReference('page.navigation.hero.hero1'));
        $this->addNavItem('Hero Two', 1, $this->getReference('page.navigation.hero.hero2'));
        $this->addReference('nav.hero', $this->entity);
        $this->getReference('hero.navigation')->setNav($this->entity);
        $this->flush();
    }

    /**
     * Return page fixtures that this nav uses
     * @return array
     */
    public function getDependencies()
    {
        return [
            HeroOne::class,
            HeroTwo::class,
            HeroNavbarPage::class
        ];
    }
}
