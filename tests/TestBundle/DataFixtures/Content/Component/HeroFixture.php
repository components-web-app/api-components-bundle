<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\Component;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Hero\HeroFactory;

class HeroFixture extends AbstractFixture
{
    /**
     * @var HeroFactory
     */
    private $heroFactory;

    public function __construct(
        HeroFactory $heroFactory
    ) {
        $this->heroFactory = $heroFactory;
    }

    public function load(ObjectManager $manager): void
    {
        $manager->persist($this->createHero());
        $manager->flush();
    }

    private function createHero()
    {
        return $this->heroFactory->create(
            [
                'title' => 'Hero Title',
                'subtitle' => 'Hero Subtitle',
                'tabs' => null
            ]
        );
    }
}
