<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Component\ContentComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\FeatureTextListComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\FormComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\Helper\FeatureHelper;
use Silverback\ApiComponentBundle\DataFixtures\Component\HeroComponent;
use Silverback\ApiComponentBundle\DataFixtures\ComponentServiceLocator;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;
use Silverback\ApiComponentBundle\Entity\Route;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;

class DummyPageFixture extends AbstractPage
{
    private $featureHelper;

    public function __construct(ComponentServiceLocator $serviceLocator, FeatureHelper $featureHelper)
    {
        parent::__construct($serviceLocator);
        $this->featureHelper = $featureHelper;
    }

    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);
        $this->entity->setTitle('Dummy Title');
        $this->entity->setMetaDescription('Dummy Meta Description');
        $this->entity->addRoute(new Route('/'));

        $this->createComponent(
            HeroComponent::class,
            $this->entity,
            [
                'title' => 'T',
                'subtitle' => 'ST'
            ]
        );

        $this->createComponent(
            ContentComponent::class,
            $this->entity,
            [
                'content' => 'Dummy content'
            ]
        );

        $this->createComponent(
            FormComponent::class,
            $this->entity,
            [
                'formType' => TestType::class,
                'successHandler' => TestHandler::class
            ]
        );

        $feature = $this->createComponent(
            FeatureTextListComponent::class,
            $this->entity
        );
        $this->featureHelper->createItem($feature, 'Feature label', '/', 1);

        $this->flush();
    }
}
