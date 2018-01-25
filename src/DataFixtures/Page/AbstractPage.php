<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Page;

use Doctrine\Common\Persistence\ObjectManager;
use GuzzleHttp\Client;
use Silverback\ApiComponentBundle\DataFixtures\AbstractFixture;
use Silverback\ApiComponentBundle\DataFixtures\CustomEntityInterface;
use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\Component\Content;
use Silverback\ApiComponentBundle\Entity\Component\FeatureHorizontal\FeatureColumns;
use Silverback\ApiComponentBundle\Entity\Component\FeatureList\FeatureTextList;
use Silverback\ApiComponentBundle\Entity\Component\FeatureMedia\FeatureStacked;
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
     * @var \GuzzleHttp\Client
     */
    private $client;

    public function __construct()
    {
        $this->client = new Client();
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

    protected function addContent (array $ops = null)
    {
        if (!$ops) {
            $ops = ['5', 'medium', 'headers', 'code', 'decorate', 'link', 'bq', 'ul', 'ol'];
        }
        $textBlock = new Content();
        $this->setOwner($textBlock);
        $res = $this->client->request('GET', 'http://loripsum.net/api/' . join('/', $ops));
        $textBlock->setContent($res->getBody());
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

    protected function addForm (string $formType, string $successHandler)
    {
        $form = new Form();
        $this->setOwner($form);
        $form->setFormType($formType);
        $form->setSuccessHandler($successHandler);
        $this->manager->persist($form);
        return $form;
    }

    protected function addFeatureHorizontal () {
        $feature = new FeatureColumns();
        $this->setOwner($feature);
        $this->manager->persist($feature);
        return $feature;
    }

    protected function addFeatureList () {
        $feature = new FeatureTextList();
        $this->setOwner($feature);
        $this->manager->persist($feature);
        return $feature;
    }

    protected function addFeatureMedia () {
        $feature = new FeatureStacked();
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
