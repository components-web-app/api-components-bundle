<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Component\ContentComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\FormComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\HeroComponent;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;
use Silverback\ApiComponentBundle\Entity\Route;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;

class DummyPageFixture extends AbstractPage
{
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

        $this->flush();
    }
}
