<?php

namespace Silverback\ApiComponentBundle\Tests\src\DataFixtures\Page\Navigation\Hero;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;

class HeroTwo extends AbstractPage implements DependentFixtureInterface
{
    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);

        $this->entity->setTitle('Hero Two');
        $this->entity->setMetaDescription('Hero Link Two');
        $this->entity->setParent($this->getReference('page.navigation.hero'));
        $this->addContent();
        $this->flush();
        $this->addReference('page.navigation.hero.hero2', $this->entity);
    }

    public function getDependencies()
    {
        return [
            HeroNavbarPage::class
        ];
    }
}
