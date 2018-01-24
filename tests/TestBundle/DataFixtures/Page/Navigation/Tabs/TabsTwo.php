<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page\Navigation\Tabs;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;

class TabsTwo extends AbstractPage implements DependentFixtureInterface
{
    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);

        $this->entity->setTitle('Tabs Two');
        $this->entity->setMetaDescription('Tabs Link Two');
        $this->entity->setParent($this->getReference('page.navigation.tabs'));
        $this->addContent();
        $this->flush();
        $this->addReference('page.navigation.tabs.tab2', $this->entity);
    }

    public function getDependencies()
    {
        return [
            TabsNavbarPage::class
        ];
    }
}
