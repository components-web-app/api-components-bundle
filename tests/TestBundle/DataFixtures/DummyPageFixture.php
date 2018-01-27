<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Factory\Component\ContentFactory;
use Silverback\ApiComponentBundle\Factory\Component\FeatureTextListFactory;
use Silverback\ApiComponentBundle\Factory\Component\FormFactory;
use Silverback\ApiComponentBundle\Factory\Component\Item\FeatureItemFactory;
use Silverback\ApiComponentBundle\Factory\Component\HeroFactory;
use Silverback\ApiComponentBundle\DataFixtures\ComponentServiceLocator;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;
use Silverback\ApiComponentBundle\Entity\Route;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;

class DummyPageFixture extends AbstractPage
{
    private $featureHelper;

    public function __construct(ComponentServiceLocator $serviceLocator, FeatureItemFactory $featureHelper)
    {
        parent::__construct($serviceLocator);
        $this->featureHelper = $featureHelper;
    }

    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);
        $this->entity->setTitle('Dummy Title');
        $this->entity->setMetaDescription('Dummy Meta Description');
        $this->entity->addRoute(new Route('/'));

        $this->createComponent(
            HeroFactory::class,
            $this->entity,
            [
                'title' => 'T',
                'subtitle' => 'ST'
            ]
        );

        $this->createComponent(
            ContentFactory::class,
            $this->entity,
            [
                'content' => 'Dummy content'
            ]
        );

        $this->createComponent(
            FormFactory::class,
            $this->entity,
            [
                'formType' => TestType::class,
                'successHandler' => TestHandler::class
            ]
        );

        $feature = $this->createComponent(
            FeatureTextListFactory::class,
            $this->entity
        );
        $this->featureHelper->createItem($feature, 'Feature label', '/', 1);

        $this->flush();
    }
}
