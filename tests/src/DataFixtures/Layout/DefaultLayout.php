<?php

namespace Silverback\ApiComponentBundle\Tests\src\DataFixtures\Layout;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Layout\AbstractLayout;

class DefaultLayout extends AbstractLayout
{
    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);
        $this->entity->setDefault(true);
        $this->flush();
        $this->addReference('layout.default', $this->entity);
    }
}