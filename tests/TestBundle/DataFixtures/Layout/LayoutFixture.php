<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Layout;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Factory\Entity\Layout\LayoutFactory;

class LayoutFixture extends AbstractFixture
{
    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    public function __construct(
        LayoutFactory $layoutFactory
    ) {
        $this->layoutFactory = $layoutFactory;
    }

    public function load(ObjectManager $manager): void
    {
        $layout = $this->createLayout();
        $manager->persist($layout);
        $this->addReference('layout', $layout);

        $manager->flush();
    }

    private function createLayout(bool $default = false, NavBar $navBar = null)
    {
        return $this->layoutFactory->create(
            [
                'default' => $default,
                'navBar' => $navBar
            ]
        );
    }
}
