<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Page;

use Silverback\ApiComponentBundle\DataFixtures\AbstractFixture;
use Silverback\ApiComponentBundle\DataFixtures\CustomEntityInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\Component\Content;
use Silverback\ApiComponentBundle\Entity\Component\FeatureHorizontal\FeatureHorizontal;
use Silverback\ApiComponentBundle\Entity\Component\FeatureList\FeatureList;
use Silverback\ApiComponentBundle\Entity\Component\FeatureMedia\FeatureMedia;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Entity\Component\Gallery\Gallery;
use Silverback\ApiComponentBundle\Entity\Component\Hero;
use Silverback\ApiComponentBundle\Entity\Component\News\News;
use Silverback\ApiComponentBundle\Entity\Page;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Symfony\Component\Form\AbstractType;

/**
 * Class AbstractPage
 * @package App\DataFixtures\Page
 * @author Daniel West <daniel@silverback.is>
 * @property Page $entity
 */
abstract class AbstractPage extends AbstractFixture
{
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

    protected function addContent (array $ops = null)
    {
        if (!$ops) {
            $ops = ['5', 'medium', 'headers', 'code', 'decorate', 'link', 'bq', 'ul', 'ol'];
        }
        $textBlock = new Content();
        $this->setOwner($textBlock);
        $textBlock->setContent(file_get_contents('http://loripsum.net/api/' . join('/', $ops)));
        $this->manager->persist($textBlock);
        return $textBlock;
    }

    protected function addHero (string $title, string $subtitle = null)
    {
        $hero = new Hero();
        $this->setOwner($hero);
        $hero->setTitle($title);
        $hero->setSubtitle($subtitle);
        $this->manager->persist($hero);
        return $hero;
    }

    protected function addForm (AbstractType $formType, FormHandlerInterface $successHandler)
    {
        $form = new Form();
        $this->setOwner($form);
        $form->setClassName($formType);
        $form->setSuccessHandler($successHandler);
        $this->manager->persist($form);
        return $form;
    }

    protected function addFeatureHorizontal () {
        $feature = new FeatureHorizontal();
        $this->setOwner($feature);
        $this->manager->persist($feature);
        return $feature;
    }

    protected function addFeatureList () {
        $feature = new FeatureList();
        $this->setOwner($feature);
        $this->manager->persist($feature);
        return $feature;
    }

    protected function addFeatureMedia () {
        $feature = new FeatureMedia();
        $this->setOwner($feature);
        $this->manager->persist($feature);
        return $feature;
    }

    protected function addGallery () {
        $feature = new Gallery();
        $this->setOwner($feature);
        $this->manager->persist($feature);
        return $feature;
    }

    protected function addNews () {
        $feature = new News();
        $this->setOwner($feature);
        $this->manager->persist($feature);
        return $feature;
    }

    private function setOwner(Component &$component) {
        if ($this->entity instanceof ComponentGroup) {
            $component->setGroup($this->entity);
        } else {
            $component->setPage($this->entity);
        }
    }
}
