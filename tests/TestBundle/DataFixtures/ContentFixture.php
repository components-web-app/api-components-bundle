<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Content\ContentFactory;

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

    public function load(ObjectManager $manager)
    {
        $content = $this->contentFactory->create(
            [
                'content' => self::DUMMY_CONTENT
            ]
        );
        $manager->persist($content);
        $manager->flush();
    }
}
