<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;

class NewsPage extends AbstractPage
{
    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);

        $this->entity->setTitle('News / Blog');
        $this->entity->setMetaDescription('News or blog components');
        $hero = $this->addHero('News / Blog', 'An example of a news/blog component');
        $hero->setClassName('is-light is-bold');
        $this->addNews();

        $this->flush();
        $this->addReference('page.news', $this->entity);
    }
}
