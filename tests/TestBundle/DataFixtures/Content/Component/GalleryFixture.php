<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\Component;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Content\Component\Gallery\Gallery;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Gallery\GalleryFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Gallery\GalleryItemFactory;

class GalleryFixture extends AbstractFixture
{
    /**
     * @var GalleryFactory
     */
    private $galleryFactory;
    /**
     * @var GalleryItemFactory
     */
    private $galleryItemFactory;
    /**
     * @var string
     */
    private $projectDir;

    public function __construct(
        GalleryFactory $galleryFactory,
        GalleryItemFactory $galleryItemFactory,
        string $projectDir = ''
    ) {
        $this->galleryFactory = $galleryFactory;
        $this->galleryItemFactory = $galleryItemFactory;
        $this->projectDir = $projectDir;
    }

    public function load(ObjectManager $manager): void
    {
        $gallery = $this->createGallery();
        $this->createGalleryItem($gallery);

        $manager->flush();
    }

    private function createGallery()
    {
        return $this->galleryFactory->create();
    }

    private function createGalleryItem(Gallery $gallery = null)
    {
        return $this->galleryItemFactory->create(
            [
                'title' => 'Gallery Item Title',
                'caption' => 'Item Caption',
                'filePath' => $this->projectDir . '/public/images/testImage.jpg',
                'parentComponent' => [$gallery, 0]
            ]
        );
    }
}
