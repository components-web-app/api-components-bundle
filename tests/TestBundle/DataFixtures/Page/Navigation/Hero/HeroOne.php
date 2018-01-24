<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page\Navigation\Hero;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;

class HeroOne extends AbstractPage implements DependentFixtureInterface
{
    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);

        $this->entity->setTitle('Hero One');
        $this->entity->setMetaDescription('Hero Link One');
        $this->entity->setParent($this->getReference('page.navigation.hero'));
        $this->addContent();
        $this->flush();
        $this->redirectFrom($this->getReference('page.navigation'));
        $this->redirectFrom($this->getReference('page.navigation.hero'));
        $this->addReference('page.navigation.hero.hero1', $this->entity);
    }

    public function getDependencies()
    {
        return [
            HeroNavbarPage::class
        ];
    }
}
