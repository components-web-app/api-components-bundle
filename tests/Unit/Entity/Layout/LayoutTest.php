<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Layout;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Entity\Layout\NavBar\NavBar;

class LayoutTest extends TestCase
{
    public function test_getters_and_setters()
    {
        $layout = new Layout();
        $this->assertTrue(ctype_xdigit($layout->getId()));
        $layout->setDefault(true);
        $this->assertTrue($layout->isDefault());
        $NavBar = new NavBar();
        $layout->setNavBar($NavBar);
        $this->assertEquals($NavBar, $layout->getNavBar());
    }
}
