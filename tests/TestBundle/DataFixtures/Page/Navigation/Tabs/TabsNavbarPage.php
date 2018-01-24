<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page\Navigation\Tabs;

use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page\NavigationPage;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;

class TabsNavbarPage extends AbstractPage implements DependentFixtureInterface
{
    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);

        $this->entity->setTitle('Tabs Navbar');
        $this->entity->setMetaDescription('An example of adding a tab navigation to a page');
        $this->entity->setParent($this->getReference('page.navigation'));
        $this->addHero('Tabs', 'You can add tabs to your page');

        $this->flush();
        $this->addReference('page.navigation.tabs', $this->entity);
    }

    public function getDependencies()
    {
        return [
            NavigationPage::class
        ];
    }
}
