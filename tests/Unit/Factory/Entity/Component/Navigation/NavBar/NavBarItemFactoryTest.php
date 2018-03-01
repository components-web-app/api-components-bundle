<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Navigation\NavBar;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Navigation\NavBar\NavBarItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class NavBarItemFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = NavBarItemFactory::class;
        $this->testOps = [
            'label' => 'Dummy label'
        ];
        parent::setUp();
    }
}
