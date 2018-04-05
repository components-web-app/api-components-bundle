<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\Component;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Content\Dynamic\ArticlePage;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Collection\CollectionFactory;

class CollectionFixture extends AbstractFixture
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    public function load(ObjectManager $manager): void
    {
        $this->createCollection();
        $manager->flush();
    }

    private function createCollection()
    {
        return $this->collectionFactory->create(
            [
                'resource' => ArticlePage::class
            ]
        );
    }
}
