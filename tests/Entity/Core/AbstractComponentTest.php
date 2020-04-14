<?php

declare(strict_types=1);

namespace Entity\Core;

use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Entity\Core\AbstractComponent;
use PHPUnit\Framework\TestCase;

class AbstractComponentTest extends TestCase
{
    protected $anonymousClassFromAbstract;

    protected function setUp(): void
    {
        // Create a new instance from the Abstract Class
        $this->anonymousClassFromAbstract = new class extends AbstractComponent {};
    }

    public function test__construct()
    {
        $this->assertInstanceOf(ArrayCollection::class, $this->anonymousClassFromAbstract->componentGroups);
        $this->assertCount(0, $this->anonymousClassFromAbstract->componentGroups);
        $this->assertIsString($this->anonymousClassFromAbstract->getId());
    }
}
