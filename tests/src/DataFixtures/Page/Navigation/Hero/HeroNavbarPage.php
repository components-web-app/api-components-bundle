<?php

namespace Silverback\ApiComponentBundle\Tests\src\DataFixtures\Page\Navigation\Hero;

use Silverback\ApiComponentBundle\Tests\src\DataFixtures\Page\NavigationPage;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;

class HeroNavbarPage extends AbstractPage implements DependentFixtureInterface
{
    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);

        $this->entity->setTitle('Hero Navbar');
        $this->entity->setMetaDescription('You can add navigation components with the BW Starter websites.');
        $this->entity->setParent($this->getReference('page.navigation'));
        $hero = $this->addHero('Hero Navbar', 'You can add a navigation component into the hero');

        $this->flush();
        $this->addReference('page.navigation.hero', $this->entity);
        $this->addReference('hero.navigation', $hero);
    }

    public function getDependencies()
    {
        return [
            NavigationPage::class
        ];
    }
}
