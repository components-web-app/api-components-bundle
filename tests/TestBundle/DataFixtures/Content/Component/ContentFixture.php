<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\Component;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Content\ContentFactory;

class ContentFixture extends AbstractFixture
{
    public const DUMMY_CONTENT = 'DUMMY CONTENT';

    /**
     * @var ContentFactory
     */
    private $contentFactory;

    public function __construct(
        ContentFactory $contentFactory
    ) {
        $this->contentFactory = $contentFactory;
    }

    public function load(ObjectManager $manager): void
    {
        $manager->persist($this->createContent());
        $manager->flush();
    }

    private function createContent()
    {
        return $this->contentFactory->create(
            [
                'content' => self::DUMMY_CONTENT
            ]
        );
    }
}
