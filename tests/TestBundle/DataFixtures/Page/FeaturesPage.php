<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;

class FeaturesPage extends AbstractPage
{
    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);

        $this->entity->setTitle('Feature Components');
        $this->entity->setMetaDescription('We have 3 ways of listing features to choose from');
        $hero = $this->addHero('Feature Components', 'We have 3 ways of listing features to choose from');
        $hero->setClassName('is-warning is-bold');
        $this->addFeatureHorizontal();
        $this->addFeatureList();
        $this->addFeatureMedia();

        $this->flush();
        $this->addReference('page.features', $this->entity);
    }
}
