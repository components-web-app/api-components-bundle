<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;

class NavigationPage extends AbstractPage
{
    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);

        $this->entity->setTitle('Navigation');
        $this->entity->setMetaDescription('There are many navigation options');
        $this->flush();
        $this->addReference('page.navigation', $this->entity);
    }
}
