<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Entity\Core;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\Core\AbstractComponent;

class AbstractComponentTest extends TestCase
{
    protected $anonymousClassFromAbstract;

    protected function setUp(): void
    {
        // Create a new instance from the Abstract Class
        $this->anonymousClassFromAbstract = new class() extends AbstractComponent {
        };
    }

    public function testConstruct()
    {
        $this->assertInstanceOf(ArrayCollection::class, $this->anonymousClassFromAbstract->componentGroups);
        $this->assertCount(0, $this->anonymousClassFromAbstract->componentGroups);
        $this->assertIsString($this->anonymousClassFromAbstract->getId());
    }
}
