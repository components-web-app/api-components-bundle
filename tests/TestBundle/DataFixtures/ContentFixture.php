<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Component\Gallery\Gallery;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Article\ArticleFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Content\ContentFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\Columns\FeatureColumnsFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\Stacked\FeatureStackedFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\TextList\FeatureTextListFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Form\FormFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Gallery\GalleryFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Gallery\GalleryItemFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Hero\HeroFactory;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;

class ContentFixture extends AbstractFixture
{
    public const DUMMY_CONTENT = 'DUMMY CONTENT';

    /**
     * @var ArticleFactory
     */
    private $articleFactory;
    /**
     * @var ContentFactory
     */
    private $contentFactory;
    /**
     * @var FormFactory
     */
    private $formFactory;
    /**
     * @var FeatureColumnsFactory
     */
    private $featureColumnsFactory;
    /**
     * @var FeatureStackedFactory
     */
    private $featureStackedFactory;
    /**
     * @var FeatureTextListFactory
     */
    private $featureTextListFactory;
    /**
     * @var GalleryFactory
     */
    private $galleryFactory;
    /**
     * @var GalleryItemFactory
     */
    private $galleryItemFactory;
    /**
     * @var HeroFactory
     */
    private $heroFactory;
    /**
     * @var string
     */
    private $projectDirectory;

    public function __construct(
        ArticleFactory $articleFactory,
        ContentFactory $contentFactory,
        FeatureColumnsFactory $featureColumnsFactory,
        FeatureStackedFactory $featureStackedFactory,
        FeatureTextListFactory $featureTextListFactory,
        FormFactory $formFactory,
        GalleryFactory $galleryFactory,
        GalleryItemFactory $galleryItemFactory,
        HeroFactory $heroFactory,
        string $projectDirectory
    ) {
        $this->articleFactory = $articleFactory;
        $this->contentFactory = $contentFactory;
        $this->featureColumnsFactory = $featureColumnsFactory;
        $this->featureStackedFactory = $featureStackedFactory;
        $this->featureTextListFactory = $featureTextListFactory;
        $this->formFactory = $formFactory;
        $this->galleryFactory = $galleryFactory;
        $this->galleryItemFactory = $galleryItemFactory;
        $this->heroFactory = $heroFactory;
        $this->projectDirectory = $projectDirectory;
    }

    public function load(ObjectManager $manager): void
    {
        $manager->persist($this->createArticle());
        $manager->persist($this->createContent());
        $manager->persist($this->createFeatureColumns());
        $manager->persist($this->createFeatureStacked());
        $manager->persist($this->createFeatureTextList());
        $manager->persist($this->createForm());
        $manager->persist($this->createGallery());
        $manager->persist($this->createGalleryItem());
        $manager->persist($this->createHero());

        $manager->flush();
    }

    private function createArticle()
    {
        return $this->articleFactory->create(
            [
                'title' => 'Article Title',
                'subtitle' => 'Article Subtitle',
                'content' => 'Content',
                'filePath' => $this->projectDirectory . '/public/images/testImage.jpg'
            ]
        );
    }

    private function createContent()
    {
        return $this->contentFactory->create(
            [
                'content' => self::DUMMY_CONTENT
            ]
        );
    }

    private function createForm()
    {
        return $this->formFactory->create(
            [
                'formType' => TestType::class,
                'successHandler' => TestHandler::class
            ]
        );
    }

    private function createFeatureColumns()
    {
        return $this->featureColumnsFactory->create(
            [
                'columns' => 3,
                'title' => 'Column features title'
            ]
        );
    }

    private function createFeatureStacked()
    {
        return $this->featureStackedFactory->create(
            [
                'reverse' => true
            ]
        );
    }

    private function createFeatureTextList()
    {
        return $this->featureTextListFactory->create(
            [
                'title' => 'Text list features title'
            ]
        );
    }

    private function createGallery()
    {
        return $this->galleryFactory->create();
    }

    private function createGalleryItem()
    {
        return $this->galleryItemFactory->create(
            [
                'title' => 'Gallery Item Title',
                'caption' => 'Item Caption',
                'filePath' => '/public/images/testImage.jpg'
            ]
        );
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
