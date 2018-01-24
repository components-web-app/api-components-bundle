<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;

class FormPage extends AbstractPage
{
    /**
     * @var TestType
     */
    private $formType;
    /**
     * @var TestHandler
     */
    private $handler;

    public function __construct(
        TestType $formType,
        TestHandler $handler
    )
    {
        parent::__construct();
        $this->formType = $formType;
        $this->handler = $handler;
    }

    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);

        $this->entity->setTitle('Forms');
        $this->entity->setMetaDescription('Forms can be handles by the BW Starter Website API including validation');
        $hero = $this->addHero('Forms', 'An example of a Symfony form served and handled by the API with validation');
        $hero->setClassName('is-success is-bold');
        $this->addContent(['2', 'short', 'decorate']);
        $this->addForm($this->formType, $this->handler);

        $this->flush();
        $this->addReference('page.forms', $this->entity);
    }
}
