<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Content\ContentFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Form\FormFactory;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;

class ContentFixture extends AbstractFixture
{
    public const DUMMY_CONTENT = 'DUMMY CONTENT';

    /**
     * @var ContentFactory
     */
    private $contentFactory;
    /**
     * @var FormFactory
     */
    private $formFactory;

    public function __construct(
        ContentFactory $contentFactory,
        FormFactory $formFactory
    ) {
        $this->contentFactory = $contentFactory;
        $this->formFactory = $formFactory;
    }

    public function load(ObjectManager $manager): void
    {
        $content = $this->contentFactory->create(
            [
                'content' => self::DUMMY_CONTENT
            ]
        );
        $manager->persist($content);
        $form = $this->formFactory->create(
            [
                'formType' => TestType::class,
                'successHandler' => TestHandler::class
            ]
        );
        $manager->persist($form);
        $manager->flush();
    }
}
