<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Page;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\AbstractFixture;
use Silverback\ApiComponentBundle\DataFixtures\Component\ContentComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\FeatureColumnsComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\FeatureStackedComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\FeatureTextListComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\FormComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\GalleryComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\HeroComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\NewsComponent;
use Silverback\ApiComponentBundle\DataFixtures\CustomEntityInterface;
use Silverback\ApiComponentBundle\Entity\Page;

/**
 * Class AbstractPage
 * @package App\DataFixtures\Page
 * @author Daniel West <daniel@silverback.is>
 * @property Page $entity
 */
abstract class AbstractPage extends AbstractFixture
{

    private $heroComponent;
    private $contentComponent;
    private $featureStackedComponent;
    private $featureColumnsComponent;
    private $featureTextListComponent;
    private $formComponent;
    private $galleryComponent;
    private $newsComponent;

    public function __construct(
        HeroComponent $heroComponent,
        ContentComponent $contentComponent,
        FeatureStackedComponent $featureStackedComponent,
        FeatureColumnsComponent $featureColumnsComponent,
        FeatureTextListComponent $featureTextListComponent,
        FormComponent $formComponent,
        GalleryComponent $galleryComponent,
        NewsComponent $newsComponent
    )
    {
        $this->heroComponent = $heroComponent;
        $this->contentComponent = $contentComponent;
        $this->featureStackedComponent = $featureStackedComponent;
        $this->featureColumnsComponent = $featureColumnsComponent;
        $this->featureTextListComponent = $featureTextListComponent;
        $this->formComponent = $formComponent;
        $this->galleryComponent = $galleryComponent;
        $this->newsComponent = $newsComponent;
    }

    /**
     * @var bool
     */
    protected $flushed = false;

    /**
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);
        if ($this instanceof CustomEntityInterface) {
            $this->entity = $this->getEntity();
        } else {
            $this->entity = new Page();
        }
    }

    protected function flush ()
    {
        parent::flush();
        $this->flushed = true;
    }

    protected function redirectFrom (Page $redirectFrom)
    {
        if (!$this->flushed) {
            throw new \BadMethodCallException('You should only call the redirectFrom method after flushing');
        }
        $redirectFrom->getRoutes()->first()->setRedirect($this->entity->getRoutes()->first());
        $this->manager->flush();
    }
}
