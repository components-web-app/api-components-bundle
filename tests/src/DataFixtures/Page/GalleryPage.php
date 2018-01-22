<?php

namespace Silverback\ApiComponentBundle\Tests\src\DataFixtures\Page;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;

class GalleryPage extends AbstractPage
{
    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);

        $this->entity->setTitle('Gallery');
        $this->entity->setMetaDescription('Image gallery component');
        $hero = $this->addHero('Gallery', 'Here you can see an image gallery');
        $hero->setClassName('is-danger is-bold');
        $this->addGallery();

        $this->flush();
        $this->addReference('page.gallery', $this->entity);
    }
}
