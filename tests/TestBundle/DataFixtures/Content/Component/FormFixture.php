<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\Component;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Form\FormFactory;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;

class FormFixture extends AbstractFixture
{
    /**
     * @var FormFactory
     */
    private $formFactory;

    public function __construct(
        FormFactory $formFactory
    ) {
        $this->formFactory = $formFactory;
    }

    public function load(ObjectManager $manager): void
    {
        $manager->persist($this->createForm());
        $manager->flush();
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
}
